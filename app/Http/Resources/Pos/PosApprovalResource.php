<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A manager's approval at the cashier's register (Doc 18 Phase I2), as
 * PosApprovals::approve() gives it: the token the cashier's requests carry,
 * what it approves, and who approved.
 */
class PosApprovalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'token'    => $this->resource['token'],
            'action'   => $this->resource['action'],
            'approver' => ['id' => $this->resource['approver']->id, 'name' => $this->resource['approver']->name],
        ];
    }
}
