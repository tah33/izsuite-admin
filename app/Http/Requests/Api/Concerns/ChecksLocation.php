<?php

namespace App\Http\Requests\Api\Concerns;

use App\Models\User\Workspace;
use App\Repositories\User\LocationRepository;
use Illuminate\Validation\Validator;

/**
 * Something new is made at an active location (Doc 12 section 19). A record
 * keeps the location it already has, active or not: an inactive location
 * keeps its transactions, and they can still be edited and settled there.
 */
trait ChecksLocation
{
    /** $current is the location the record has now - null for a new one. */
    protected function checkLocation(Validator $validator, Workspace $workspace, mixed $locationId, ?int $current): void
    {
        if (! $locationId || (int) $locationId === $current) {
            return;
        }

        $location = app(LocationRepository::class)->findFor($workspace, (int) $locationId);

        if ($location && ! $location->is_active) {
            $validator->errors()->add('location_id', "{$location->name} is inactive. Choose an active location.");
        }
    }
}
