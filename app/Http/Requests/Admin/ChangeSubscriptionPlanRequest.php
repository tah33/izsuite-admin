<?php

namespace App\Http\Requests\Admin;

use App\Models\Billing\WorkspaceSubscription;
use Illuminate\Validation\Rule;

/** A subscription moved to another plan or cycle by izSuite (Doc 15 section 15.13), and why. */
class ChangeSubscriptionPlanRequest extends SubscriptionReasonRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'cycle'      => ['required', Rule::in(WorkspaceSubscription::CYCLES)],
        ];
    }
}
