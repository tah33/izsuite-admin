<?php

namespace App\Http\Requests\Api\Members;

/**
 * An invitation (Doc 12 section 6): what a change takes, and the e-mail it
 * goes to. One e-mail is invited once per business (MemberService).
 */
class InviteMemberRequest extends UpdateMemberRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
