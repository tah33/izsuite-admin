<?php

namespace App\Services\Crm;

use App\Models\User\CrmCustomField;
use App\Models\User\Workspace;
use App\Repositories\User\CrmCustomFieldRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * A lead's or opportunity's custom fields (Doc 17 Phase I4): what its form
 * asks - the business's fields for it switched on, in their order - the
 * values it keeps, each checked to be of its field's type, and what its page
 * shows. Values are kept by field id: text, a number and a choice as
 * strings, a date as Y-m-d, yes or no as true or false. A field switched off
 * keeps its values, hidden; an emptied one is dropped.
 */
class CustomFieldValues
{
    /** How long a text value may be. */
    public const TEXT_LENGTH = 1000;

    public function __construct(
        protected CrmCustomFieldRepository $fields,
    ) {}

    /** $record's fields switched on, in their order: what its form asks. */
    public function forForm(Workspace $workspace, string $record): Collection
    {
        return $this->fields->activeFor($workspace, $record);
    }

    /**
     * The values a record keeps: $current's, each of $record's fields switched
     * on taking $input's - checked to be of its type, a required one filled -
     * and one emptied gone. A choice may keep the value it had when its field
     * no longer offers it. Refused with each field's error, under
     * custom_fields.{id}. Null when it keeps none.
     *
     * @param  array<int|string, mixed>  $input
     * @param  array<int|string, mixed>  $current
     * @return array<int, string|bool>|null
     */
    public function checked(Workspace $workspace, string $record, array $input, array $current = []): ?array
    {
        $values = $current;
        $errors = [];

        foreach ($this->fields->activeFor($workspace, $record) as $field) {
            $raw = $input[$field->id] ?? null;
            $raw = is_string($raw) ? trim($raw) : $raw;

            if ($raw === null || $raw === '') {
                unset($values[$field->id]);

                if ($field->is_required) {
                    $errors["custom_fields.{$field->id}"] = $field->type === 'yes_no' ? "Answer {$field->name}." : "Fill in {$field->name}.";
                }

                continue;
            }

            [$value, $error] = $this->typed($field, $raw, $current[$field->id] ?? null);

            if ($error !== null) {
                $errors["custom_fields.{$field->id}"] = $error;

                continue;
            }

            $values[$field->id] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $values === [] ? null : $values;
    }

    /**
     * $record's fields switched on, in their order, each with its value in
     * $values - null without one: what a lead's or opportunity's page shows.
     *
     * @param  array<int|string, mixed>|null  $values
     * @return list<array{id: int, name: string, type: string, value: string|bool|null}>
     */
    public function shown(Workspace $workspace, string $record, ?array $values): array
    {
        return $this->fields->activeFor($workspace, $record)
            ->map(fn (CrmCustomField $field) => ['id' => $field->id, 'name' => $field->name, 'type' => $field->type, 'value' => $values[$field->id] ?? null])
            ->values()
            ->all();
    }

    /**
     * $raw as $field keeps it, with the error that refuses it, if any.
     *
     * @return array{0: string|bool|null, 1: string|null}
     */
    private function typed(CrmCustomField $field, mixed $raw, mixed $had): array
    {
        return match ($field->type) {
            'text'   => is_scalar($raw) && mb_strlen((string) $raw) <= self::TEXT_LENGTH
                ? [(string) $raw, null]
                : [null, 'Keep it to '.number_format(self::TEXT_LENGTH).' characters.'],
            'number' => is_numeric($raw) && abs((float) $raw) < 1e13
                ? [(string) $raw, null]
                : [null, 'Enter a number.'],
            'date'   => is_string($raw) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $day) && checkdate((int) $day[2], (int) $day[3], (int) $day[1])
                ? [$raw, null]
                : [null, 'Enter a date.'],
            'choice' => is_string($raw) && (in_array($raw, $field->options ?? [], true) || $raw === $had)
                ? [$raw, null]
                : [null, 'Choose one of its options.'],
            'yes_no' => in_array($raw, [true, false, 1, 0, '1', '0'], true)
                ? [(bool) $raw, null]
                : [null, 'Answer yes or no.'],
            default  => [null, 'This field cannot be filled in.'],
        };
    }
}
