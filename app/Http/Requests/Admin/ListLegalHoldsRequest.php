<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin > Legal Holds' filters (Doc 15 Phase G2): the business or the reference, and in force or released. */
class ListLegalHoldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'state'  => ['nullable', Rule::in(['active', 'released'])],
        ];
    }
}
