<?php

namespace App\Http\Requests\Api\Documents;

use App\Enums\NumberingPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveNumberingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** No prefix is an empty one, never "unknown". */
    protected function prepareForValidation(): void
    {
        $this->merge(['prefix' => trim((string) $this->input('prefix', ''))]);
    }

    /**
     * How a kind of document is numbered (Doc 11 section 21), for a save and
     * for a preview of it alike. A prefix is up to 20 letters, digits and
     * # / . _ - and may be empty; a number starts at 1 or above and is padded
     * to 1 to 10 digits.
     */
    public function rules(): array
    {
        return [
            'prefix'          => ['string', 'max:20', 'regex:/^[\pL\pN#\/._-]*$/u'],
            'starting_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'number_length'   => ['required', 'integer', 'min:1', 'max:10'],
            'period'          => ['required', Rule::enum(NumberingPeriod::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'starting_number' => 'starting number',
            'number_length'   => 'number length',
        ];
    }

    public function messages(): array
    {
        return [
            'prefix.regex'        => 'A prefix uses letters, numbers and # / . _ - only, such as INV-.',
            'number_length.*'     => 'A number has 1 to 10 digits.',
            'starting_number.min' => 'Numbers start at 1 or above.',
            'period.*'            => 'Choose whether numbers carry a period.',
        ];
    }
}
