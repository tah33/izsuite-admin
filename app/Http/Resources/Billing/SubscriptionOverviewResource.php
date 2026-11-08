<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The business's subscription as Settings shows it (Doc 13 section 11): its
 * plan and status, the plans to compare it with - its own marked - and its
 * billing details, null until first saved.
 *
 * @property array{package: array|null, status: string, packages: list<array>, details: mixed} $resource
 */
class SubscriptionOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'package'  => $this->resource['package'] ? [
                'name'             => $this->resource['package']['name'],
                'removes_branding' => $this->resource['package']['removes_branding'],
            ] : null,
            'status'   => $this->resource['status'],
            'packages' => PackageOfferResource::collection($this->resource['packages']),
            'details'  => $this->resource['details'] ? new BillingDetailsResource($this->resource['details']) : null,
        ];
    }
}
