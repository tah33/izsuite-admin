<?php

namespace App\Http\Requests\Admin;

use App\Models\Billing\WorkspaceSubscription;
use App\Support\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin > Subscriptions' filters (Doc 15 section 15.13): status, plan, cycle, how it is paid, and words. */
class ListSubscriptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'   => ['nullable', Rule::in(array_keys(SubscriptionStatus::LABELS))],
            'package'  => ['nullable', 'string', 'max:50'],
            'cycle'    => ['nullable', Rule::in(WorkspaceSubscription::CYCLES)],
            'provider' => ['nullable', Rule::in(['card', 'transfer'])],
            'search'   => ['nullable', 'string', 'max:255'],
        ];
    }
}
