<?php

namespace App\Http\Requests\Api\Billing;

use Illuminate\Foundation\Http\FormRequest;

/** The checkout the business came back from on Stripe's page (Doc 13 Phase D2), as Stripe names it: "cs_test_...". */
class CompleteCardCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string', 'max:255', 'regex:/^cs_[A-Za-z0-9_]+$/'],
        ];
    }
}
