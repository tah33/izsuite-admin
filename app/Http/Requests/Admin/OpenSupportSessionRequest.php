<?php

namespace App\Http\Requests\Admin;

use App\Services\Shared\SupportSessionService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A read-only support session in a business (Doc 15 section 15.22): one of
 * its open cases, the reason, and how long - an hour to four.
 */
class OpenSupportSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_id' => ['required', 'integer'],
            'reason'    => ['required', 'string', 'min:5', 'max:500'],
            'hours'     => ['required', 'integer', 'min:1', 'max:'.SupportSessionService::MAX_HOURS],
        ];
    }

    public function messages(): array
    {
        return [
            'ticket_id.required' => 'Choose the case the session is for.',
            'reason.required'    => 'Give the reason. The business sees it.',
            'reason.min'         => 'Give the reason in a few words. The business sees it.',
        ];
    }
}
