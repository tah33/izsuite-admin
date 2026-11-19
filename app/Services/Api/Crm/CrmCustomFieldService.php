<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmCustomField;
use App\Models\User\Workspace;
use App\Repositories\User\CrmCustomFieldRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Support\SettingChanges;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Settings → CRM's custom fields for leads and opportunities (Doc 17
 * sections 17.6, 17.12 and 17.26 - Phase I4): listed, added at the end of
 * their record's, changed, deleted and put in another order. A field any
 * record has a value for keeps its type, and is switched off rather than
 * deleted. Each change is logged.
 */
class CrmCustomFieldService
{
    use RefusesConflicts;

    /** Whose fields, as the log names them. */
    private const WHOSE = [CrmCustomField::LEAD => 'leads', CrmCustomField::OPPORTUNITY => 'opportunities'];

    public function __construct(
        protected CrmCustomFieldRepository $fields,
    ) {}

    public function index(Workspace $workspace): Collection
    {
        return $this->fields->getFor($workspace);
    }

    /** $data is validated (see SaveCrmCustomFieldRequest); it goes last among its record's. */
    public function create(Workspace $workspace, array $data): CrmCustomField
    {
        $field = $this->fields->create($workspace, [
            ...$this->checked($workspace, $data['record'], $data),
            'record'   => $data['record'],
            'position' => $this->fields->nextPosition($workspace, $data['record']),
        ]);

        ActivityLogService::record('created', "Added the custom field \"{$field->name}\" for ".self::WHOSE[$field->record], $field, ['source' => 'api']);

        return $field;
    }

    public function update(Workspace $workspace, int $id, array $data): CrmCustomField
    {
        $field  = $this->find($workspace, $id);
        $fields = $this->checked($workspace, $field->record, $data, $field->id);

        if ($fields['type'] !== $field->type && $this->fields->hasValues($field)) {
            throw ValidationException::withMessages(['type' => ucfirst(self::WHOSE[$field->record]).' have values for it, so its type stays.']);
        }

        $this->fields->update($field, $fields);

        ActivityLogService::record('updated', "Updated the custom field \"{$field->name}\" for ".self::WHOSE[$field->record], $field, ['source' => 'api', 'changes' => SettingChanges::of($field)]);

        return $field;
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $field = $this->find($workspace, $id);

        if ($this->fields->hasValues($field)) {
            $this->refuse(ucfirst(self::WHOSE[$field->record]).' have values for it, so it stays. Switch it off instead.');
        }

        $this->fields->delete($field);

        ActivityLogService::record('deleted', "Deleted the custom field \"{$field->name}\" for ".self::WHOSE[$field->record], $field, ['source' => 'api']);
    }

    /** Puts $record's fields in the order of $ids: every one of them, once each. */
    public function reorder(Workspace $workspace, string $record, array $ids): Collection
    {
        $ids = array_map('intval', $ids);
        $own = $this->fields->idsFor($workspace, $record);

        if (count($ids) !== count($own) || array_diff($own, $ids) !== []) {
            throw ValidationException::withMessages(['ids' => 'Put every field in the order, once each.']);
        }

        $this->fields->reorder($workspace, $record, $ids);

        ActivityLogService::record('updated', 'Changed the order of the custom fields for '.self::WHOSE[$record], $workspace, ['source' => 'api']);

        return $this->fields->getFor($workspace);
    }

    private function find(Workspace $workspace, int $id): CrmCustomField
    {
        return $this->fields->findFor($workspace, $id) ?? abort(404, 'There is no such custom field.');
    }

    /** $data as a field keeps it: its name its record's own, a choice's options and no other's. */
    private function checked(Workspace $workspace, string $record, array $data, ?int $exceptId = null): array
    {
        $name = trim($data['name']);

        if ($this->fields->nameTaken($workspace, $record, $name, $exceptId)) {
            throw ValidationException::withMessages(['name' => 'Another field for '.self::WHOSE[$record].' has this name.']);
        }

        return [
            'name'        => $name,
            'type'        => $data['type'],
            'options'     => $data['type'] === 'choice' ? array_values($data['options']) : null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active'   => (bool) ($data['is_active'] ?? true),
        ];
    }
}
