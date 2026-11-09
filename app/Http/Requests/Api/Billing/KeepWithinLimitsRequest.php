<?php

namespace App\Http\Requests\Api\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The users and locations the owner keeps active within the plan (Doc 13
 * section 14): besides themselves and the Primary Location, which always
 * stay. Either list may be empty - everyone and everything else then goes.
 */
class KeepWithinLimitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_ids'     => ['array'],
            'member_ids.*'   => ['integer', 'distinct'],
            'location_ids'   => ['array'],
            'location_ids.*' => ['integer', 'distinct'],
        ];
    }
}
