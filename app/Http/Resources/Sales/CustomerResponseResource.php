<?php

namespace App\Http\Resources\Sales;

use App\Models\User\CustomerResponse;
use App\Support\DeviceName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a customer did through a document's secure link (Doc 16 Phase G2),
 * on the document's page: accepted, rejected or comment, the name they gave,
 * what they wrote, and when and where from - their address and browser.
 *
 * @mixin CustomerResponse
 */
class CustomerResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'kind'       => $this->kind,
            'name'       => $this->name,
            'body'       => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'ip_address' => $this->ip_address,
            'device'     => DeviceName::from($this->user_agent),
        ];
    }
}
