<?php

namespace App\Http\Resources\Apps;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What turning an app off will do (Doc 13 section 8), as the Apps Center's
 * confirmation says it: the Lite edition that comes back, the apps that need
 * it, the pages and reports that leave, and what is still open in it.
 *
 * @property array $resource
 */
class DeactivationImpactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'falls_back_to' => $this->resource['falls_back_to'],
            'dependents'    => $this->resource['dependents'],
            'pages'         => $this->resource['pages'],
            'reports'       => $this->resource['reports'],
            'open_items'    => $this->resource['open_items'],
        ];
    }
}
