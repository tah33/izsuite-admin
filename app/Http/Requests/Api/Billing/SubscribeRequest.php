<?php

namespace App\Http\Requests\Api\Billing;

use App\Models\Billing\WorkspaceSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A plan to subscribe to, by its key, and how it is paid: monthly or yearly (Doc 13 section 11). */
class SubscribeRequest extends FormRequest
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
