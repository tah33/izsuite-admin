<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of the Reports page (Doc 07 section 2): a report, its category,
 * what it shows and the filters it takes - or, for a page of its own, where
 * it is. Wraps ReportCatalog's entry.
 */
class ReportCatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key'         => $this->resource['key'],
            'name'        => $this->resource['name'],
            'category'    => $this->resource['category'],
            'description' => $this->resource['description'],
            'filters'     => $this->resource['filters'],
            'link'        => $this->resource['link'],
        ];
    }
}
