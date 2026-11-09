<?php

namespace App\Http\Requests\Api\Billing;

use App\Models\Billing\WorkspaceSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The plan, by its key, and cycle an active subscription would move to (Doc 13 section 13) - to see what it costs, or to make the move. */
class ChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package' => ['required', 'string', 'max:50'],
            'cycle'   => ['required', Rule::in(WorkspaceSubscription::CYCLES)],
        ];
    }
}
