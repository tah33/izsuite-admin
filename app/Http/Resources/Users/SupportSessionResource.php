<?php

namespace App\Http\Resources\Users;

use App\Models\User\SupportSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The support session a request came on (Doc 15 section 15.22), as the app's
 * banner shows it: the business, read only or with edit access, until when,
 * the case it is for, who from izSuite, and where End Session goes. Wraps
 * ['session' => SupportSession, 'back_url' => string].
 */
class SupportSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SupportSession $session */
        $session = $this->resource['session'];

        return [
            'id'            => $session->id,
            'business_id'   => $session->workspace_id,
            'business_name' => $session->workspace?->name,
            'can_edit'      => $session->canEdit(),
            'ends_at'       => to_display_timezone_iso($session->expires_at),
            'case'          => $session->ticket ? ['id' => $session->ticket->id, 'subject' => $session->ticket->subject] : null,
            'admin'         => $session->admin_name,
            'back_url'      => $this->resource['back_url'],
        ];
    }
}
