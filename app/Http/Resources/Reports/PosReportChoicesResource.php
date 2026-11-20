<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** POS's report choices (Doc 18 Phase H1): each register by its name and code, each cashier by name. */
class PosReportChoicesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'registers' => $this->resource['registers']->map(fn ($register) => ['id' => $register->id, 'name' => $register->name, 'code' => $register->code])->values(),
            'cashiers'  => $this->resource['cashiers']->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])->values(),
        ];
    }
}
