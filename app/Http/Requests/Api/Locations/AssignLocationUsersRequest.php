<?php

namespace App\Http\Requests\Api\Locations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A location's Assign Users (Doc 12 section 18): the ids of the users with
 * Selected Locations who may use it - none at all is a list too. Ids that
 * are not such users of the business are passed over (see
 * LocationUserService).
 */
class AssignLocationUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_ids'   => ['present', 'array', 'max:500'],
            'member_ids.*' => ['integer', 'distinct'],
        ];
    }
}
