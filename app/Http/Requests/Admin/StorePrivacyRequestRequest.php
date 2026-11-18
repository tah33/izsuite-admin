<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\PrivacyRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A privacy request as it came (Doc 15 Phase G1): its type, who asked and
 * how, the day it came, what they asked, and the account (by e-mail) and
 * business (by BW- ID) it is about. Which of those its type needs is checked
 * by PrivacyRequestService.
 */
class StorePrivacyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'            => ['required', Rule::in(array_keys(PrivacyRequest::TYPES))],
            'requester_name'  => ['required', 'string', 'max:150'],
            'requester_email' => ['required', 'email', 'max:190'],
            'received_via'    => ['required', Rule::in(array_keys(PrivacyRequest::CHANNELS))],
            'received_on'     => ['required', 'date', 'before_or_equal:today'],
            'details'         => ['required', 'string', 'max:5000'],
            'account_email'   => ['nullable', 'email', 'max:190'],
            'business'        => ['nullable', 'string', 'max:20', 'regex:/^\s*(BW-?)?\d+\s*$/i'],
        ];
    }

    public function messages(): array
    {
        return [
            'received_on.before_or_equal' => 'The request cannot have come on a day still to come.',
            'business.regex'              => 'Enter the business by its ID, such as BW-000012.',
        ];
    }
}
