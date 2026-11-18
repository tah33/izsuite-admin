<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The tax line of a subscription price or invoice (Doc 13 section 16 - Phase
 * D1): the tax's name and rate ("15.000"), what it adds, and - a reverse
 * charge - the note shown in its place, with nothing added.
 *
 * @property array{name: string, rate: string, amount: string, note: ?string} $resource
 */
class SubscriptionTaxLineResource extends JsonResource
{
    /** The line for $tax - null without one. */
    public static function of(array $tax): ?self
    {
        return $tax['name'] === null ? null : new self($tax);
    }

    public function toArray(Request $request): array
    {
        return [
            'name'   => $this->resource['name'],
            'rate'   => $this->resource['rate'],
            'amount' => $this->resource['amount'],
            'note'   => $this->resource['note'],
        ];
    }
}
