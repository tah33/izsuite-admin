<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmCustomField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The leads' or the opportunities' custom fields in a new order (Doc 17 Phase I4): every one of them, once each. */
class ReorderCrmCustomFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'record' => ['required', Rule::in(CrmCustomField::RECORDS)],
            'ids'    => ['required', 'array', 'max:200'],
            'ids.*'  => ['integer', 'distinct'],
        ];
    }
}
