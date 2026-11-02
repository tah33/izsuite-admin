<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * A record made at one of its business's locations (Doc 12 section 21).
 * atLocations() keeps a query to the locations a user may use: every one for
 * null, none for an empty list. A record at no location is only for those who
 * may use every location.
 */
trait AtLocation
{
    /** @param  list<int>|null  $ids */
    public function scopeAtLocations(Builder $query, ?array $ids): void
    {
        if ($ids !== null) {
            $query->whereIn($this->qualifyColumn('location_id'), $ids);
        }
    }
}
