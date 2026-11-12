<?php

namespace App\Http\Resources\Search;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One Global Search result as the header's search box shows it (Doc 14
 * section 16). It gives the kind it is grouped under and its type, its name
 * or number with a line under it, its status, date, location and source app,
 * whether it is archived, and the page it opens.
 */
class SearchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'group'    => $this->resource['group'],
            'type'     => $this->resource['type'],
            'title'    => $this->resource['title'],
            'subtitle' => $this->resource['subtitle'],
            'status'   => $this->resource['status'],
            'date'     => $this->resource['date'],
            'location' => $this->resource['location'],
            'app'      => $this->resource['app'],
            'archived' => $this->resource['archived'],
            'link'     => $this->resource['link'],
        ];
    }
}
