<?php

namespace App\Http\Resources\Countries;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A country from App\Support\Countries, as the country and phone fields pick
 * it - and as the setup takes its time zone (Doc 15 Phase F3a): the one its
 * businesses usually keep, and all it has.
 */
class CountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code'         => $this->resource['code'],
            'name'         => $this->resource['name'],
            'calling_code' => $this->resource['calling_code'],
            'region_label' => $this->resource['region_label'],
            'postal_label' => $this->resource['postal_label'],
            'timezone'     => $this->resource['timezone'],
            'timezones'    => $this->resource['timezones'],
        ];
    }
}
