<?php

namespace App\Http\Resources\Setup;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The First-Time Business Setup: the step the business has reached, whether
 * it is finished, and the currency its country pays in (a code, or null), for
 * the Base Currency step to suggest. Wraps SetupService::show().
 */
class SetupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'step'      => $this->resource['step'],
            'completed' => $this->resource['completed'],
            'currency'  => $this->resource['currency'],
        ];
    }
}
