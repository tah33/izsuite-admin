<?php

namespace App\Repositories\User;

use App\Models\User\CrmCustomField;
use App\Models\User\CrmLead;
use App\Models\User\CrmOpportunity;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/** A business's own fields for its leads and opportunities (Doc 17 Phase I4), each record's in their order. */
class CrmCustomFieldRepository
{
    /** Its fields, the leads' then the opportunities', each in their order. */
    public function getFor(Workspace $workspace): Collection
    {
        return $workspace->crmCustomFields()->orderBy('record')->orderBy('position')->orderBy('id')->get();
    }

    /** $record's fields switched on, in their order: what its form and page show. */
    public function activeFor(Workspace $workspace, string $record): Collection
    {
        return $workspace->crmCustomFields()->where('record', $record)->where('is_active', true)->orderBy('position')->orderBy('id')->get();
    }

    public function findFor(Workspace $workspace, int $id): ?CrmCustomField
    {
        return $workspace->crmCustomFields()->find($id);
    }

    /** Whether another of $record's fields - $exceptId aside - is called $name. */
    public function nameTaken(Workspace $workspace, string $record, string $name, ?int $exceptId = null): bool
    {
        return $workspace->crmCustomFields()->where('record', $record)->where('name', $name)->when($exceptId, fn ($query, $id) => $query->whereKeyNot($id))->exists();
    }

    /** The place after $record's last field. */
    public function nextPosition(Workspace $workspace, string $record): int
    {
        return (int) $workspace->crmCustomFields()->where('record', $record)->max('position') + 1;
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): CrmCustomField
    {
        return $workspace->crmCustomFields()->create($data);
    }

    public function update(CrmCustomField $field, array $data): CrmCustomField
    {
        $field->update($data);

        return $field;
    }

    public function delete(CrmCustomField $field): void
    {
        $field->delete();
    }

    /** The ids of $record's fields. @return list<int> */
    public function idsFor(Workspace $workspace, string $record): array
    {
        return $workspace->crmCustomFields()->where('record', $record)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Puts $record's fields in the order of $ids, every one of its own. */
    public function reorder(Workspace $workspace, string $record, array $ids): void
    {
        foreach (array_values($ids) as $position => $id) {
            $workspace->crmCustomFields()->where('record', $record)->whereKey($id)->update(['position' => $position + 1]);
        }
    }

    /** Whether any of its record's leads or opportunities has a value for $field: it then stays, and keeps its type. */
    public function hasValues(CrmCustomField $field): bool
    {
        $model = $field->record === CrmCustomField::LEAD ? CrmLead::class : CrmOpportunity::class;

        return $model::query()->where('workspace_id', $field->workspace_id)->whereNotNull("custom_fields->{$field->id}")->exists();
    }
}
