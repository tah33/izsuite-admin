<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A CRM e-mail template as Settings → CRM edits it (Doc 17 Phase F3b): its name, subject and message, placeholders unfilled. */
class CrmEmailTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'name'    => $this->name,
            'subject' => $this->subject,
            'body'    => $this->body,
        ];
    }
}
