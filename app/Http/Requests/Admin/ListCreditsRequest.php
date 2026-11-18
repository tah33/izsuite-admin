<?php

namespace App\Http\Requests\Admin;

use App\Models\Billing\SubscriptionCredit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin > Credits' filters (Doc 15 Phase C2b): the kind of movement, and words. */
class ListCreditsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind'   => ['nullable', Rule::in(SubscriptionCredit::KINDS)],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
