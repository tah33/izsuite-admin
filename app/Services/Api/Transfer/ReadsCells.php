<?php

namespace App\Services\Api\Transfer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Reading the cells of an import the way people fill in spreadsheets: words
 * in any case ("physical product", "Yes"), amounts with thousands separators
 * or a currency sign, and an empty cell for "nothing" - and checking the
 * result with the same form the app uses.
 */
trait ReadsCells
{
    /** Yes and no, by the usual words - for choice(). */
    protected const YES_NO = ['yes' => ['y', 'true', '1', 'on'], 'no' => ['n', 'false', '0', 'off']];

    /** The cell of $key, trimmed; null when it is empty or the column was not matched. */
    protected function text(array $cells, string $key): ?string
    {
        $value = trim((string) ($cells[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * Which of $options a cell names - each option's key with the words that
     * mean it. Null for an empty cell, false for one that names none.
     *
     * @param  array<string, list<string>>  $options
     */
    protected function choice(?string $value, array $options): string|false|null
    {
        if ($value === null) {
            return null;
        }

        $said = $this->plain($value);

        foreach ($options as $key => $words) {
            foreach ([$key, ...$words] as $word) {
                if ($this->plain($word) === $said) {
                    return $key;
                }
            }
        }

        return false;
    }

    /**
     * An amount as the API takes it ("1234.50"): a currency sign or code and
     * spaces dropped, "1,234.50" read with its thousands separator, and
     * "12,50" with a decimal comma. Anything else is passed on as it is, for
     * the form's check to name.
     */
    protected function amount(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $number = (string) preg_replace('/^[^\d\-.,]+|[^\d.,]+$/u', '', str_replace(' ', '', $value));

        return match (true) {
            (bool) preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $number) => str_replace(',', '', $number),
            (bool) preg_match('/^\d+,\d{1,2}$/', $number)              => str_replace(',', '.', $number),
            $number === ''                                             => $value,
            default                                                    => $number,
        };
    }

    /** Folded for comparing: lower case, without spaces, dashes, underscores or dots. */
    protected function plain(string $value): string
    {
        return (string) preg_replace('/[\s\-_.\/&]+/u', '', mb_strtolower(trim($value)));
    }

    /**
     * What $request's rules - the app's own form - say about $payload, the
     * first message of each field, keyed by field: "tags.2" counts as "tags".
     *
     * @param  class-string<FormRequest>  $request
     */
    protected function formErrors(string $request, array $payload, array $attributes = []): array
    {
        $form = $request::create('/', 'POST', $payload);

        foreach ($attributes as $key => $value) {
            $form->attributes->set($key, $value);
        }

        $errors = [];

        foreach (Validator::make($payload, $form->rules(), $form->messages(), $form->attributes())->errors()->messages() as $key => $messages) {
            $errors[Str::before($key, '.')] ??= $messages[0];
        }

        return $errors;
    }
}
