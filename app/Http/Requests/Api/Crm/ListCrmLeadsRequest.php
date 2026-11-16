<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Leads page's filters (Doc 17 Phase B1): a status - none for the open
 * leads, "all" for every one, "new" for the queue - an owner ("me" for the
 * user's own) and a source.
 */
class ListCrmLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'    => ['nullable', 'string', 'max:100'],
            'status'    => ['nullable', Rule::in(['all', ...CrmLead::STATUSES])],
            'owner_id'  => ['nullable', 'regex:/^(me|[0-9]+)$/'],
            'source_id' => ['nullable', 'integer'],
        ];
    }
}
