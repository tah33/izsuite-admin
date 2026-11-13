<?php

namespace App\Http\Requests\Admin;

/** A trial or a grace period extended (Doc 15 section 15.13): by how many days, and why. */
class ExtendSubscriptionRequest extends SubscriptionReasonRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'days' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'days.required' => 'Say by how many days.',
            'days.max'      => 'At most 60 days at a time.',
        ];
    }
}
