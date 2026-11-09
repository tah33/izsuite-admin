<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a plan change would do, shown before it is made (Doc 13 section 13 -
 * SubscriptionChanges::preview): up - its price, less the credit for the
 * unused part of the paid period, plus the tax on the rest, due now - or
 * down, on the day the paid period ends.
 *
 * @property array $resource
 */
class PlanChangePreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kind'         => $this->resource['kind'],
            'package'      => $this->resource['package']->name,
            'cycle'        => $this->resource['cycle'],
            'currency'     => $this->resource['currency'],
            'amount'       => $this->resource['amount'],
            'credit'       => $this->resource['credit'],
            'tax'          => SubscriptionTaxLineResource::of($this->resource['tax']),
            'total'        => $this->resource['total'],
            'effective_on' => $this->resource['effective_on'],
        ];
    }
}
