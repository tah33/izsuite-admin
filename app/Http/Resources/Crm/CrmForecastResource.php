<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The Forecast (Doc 17 section 17.14 - Phase G2), as CrmForecastService
 * gives it: the currency it is in, how it is grouped, its rows - each how
 * many are open, their expected and weighted value, and what was won this
 * month - and their total. Each value says how many were left out for want
 * of a rate today.
 */
class CrmForecastResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $money   = fn (array $value) => ['amount' => $value['total'], 'unrated' => $value['unrated']];
        $figures = fn (array $row) => [
            'count'    => $row['count'],
            'value'    => $money($row['value']),
            'weighted' => $money($row['weighted']),
            'won'      => $money($row['won']),
        ];

        return [
            'currency' => $this->resource['currency'],
            'group_by' => $this->resource['group_by'],
            'rows'     => array_map(fn (array $row) => ['key' => $row['key'], 'label' => $row['label'], ...$figures($row)], $this->resource['rows']),
            'total'    => $figures($this->resource['total']),
        ];
    }
}
