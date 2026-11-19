<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Leads page's filters (Doc 17 Phase B1): a status - none for the open
 * leads, "all" for every one, "new" for the queue, (Phase G3b)
 * "uncontacted" for the open ones not contacted in time, and (Phase G4)
 * "awaiting" for those awaiting follow-up, New or Assigned - an owner ("me"
 * for the user's own), a source and (Phase I2) a score's band: hot, warm or
 * cold.
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
            'status'    => ['nullable', Rule::in(['all', 'awaiting', 'uncontacted', ...CrmLead::STATUSES])],
            'owner_id'  => ['nullable', 'regex:/^(me|[0-9]+)$/'],
            'source_id' => ['nullable', 'integer'],
            // Phase I2: a score's band.
            'score'     => ['nullable', Rule::in(array_keys(CrmLead::SCORE_BANDS))],
        ];
    }
}
