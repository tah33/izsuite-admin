<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;

/** The lead assignment rules in a new order (Doc 17 Phase I1): every one's id, first to last. */
class ReorderCrmAssignmentRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }
}
