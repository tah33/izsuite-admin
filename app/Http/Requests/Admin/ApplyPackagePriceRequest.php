<?php

namespace App\Http\Requests\Admin;

use App\Models\Billing\WorkspaceSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A plan's price applied to its subscribers (Doc 15 Phase C3a): which
 * currency and cycle, from which day, and - a critical action - the admin's
 * password again and the reason, which CriticalActionService checks.
 */
class ApplyPackagePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency'         => ['required', 'string', 'size:3'],
            'cycle'            => ['required', Rule::in(WorkspaceSubscription::CYCLES)],
            'from'             => ['required', 'date'],
            'confirm_password' => ['nullable', 'string'],
            'reason'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
