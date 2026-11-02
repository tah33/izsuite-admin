<?php

namespace App\Http\Resources\Members;

use App\Models\User\WorkspaceMember;
use Illuminate\Http\Request;

/**
 * A user of the business as its form shows it: what the list shows, and the
 * rest of who they are - phone, job title, language and time zone.
 *
 * @mixin WorkspaceMember
 */
class MemberResource extends MemberListResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'phone'     => $this->phone,
            'job_title' => $this->job_title,
            'language'  => $this->language,
            'timezone'  => $this->timezone,
        ];
    }
}
