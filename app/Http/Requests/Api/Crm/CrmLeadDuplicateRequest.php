<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmDuplicateReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A decision about one of a lead's possible duplicates (Doc 17 Phase C2):
 * which other lead or contact, and - to ignore it - why.
 */
class CrmLeadDuplicateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'   => ['required', Rule::in(CrmDuplicateReview::TYPES)],
            'id'     => ['required', 'integer'],
            'reason' => [$this->routeIs('api.crm.leads.duplicates.ignore') ? 'required' : 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Say why it is not a duplicate.',
        ];
    }
}
