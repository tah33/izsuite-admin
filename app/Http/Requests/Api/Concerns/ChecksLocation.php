<?php

namespace App\Http\Requests\Api\Concerns;

use App\Models\User\Workspace;
use App\Repositories\User\LocationRepository;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Validation\Validator;

/**
 * Something new is made at an active location (Doc 12 section 19). A record
 * keeps the location it already has, active or not: an inactive location
 * keeps its transactions, and they can still be edited and settled there.
 *
 * A user with Selected Locations makes and moves records only to those
 * (section 20) - and no transaction at none, which they could not see again.
 * A payment account may be the whole business's ($shared).
 */
trait ChecksLocation
{
    /** $current is the location the record has now - null for a new one. */
    protected function checkLocation(Validator $validator, Workspace $workspace, mixed $locationId, ?int $current, bool $shared = false): void
    {
        $access = app(WorkspaceAccess::class);

        if (! $locationId) {
            if (! $shared && $access->locations() !== null) {
                $validator->errors()->add('location_id', 'Choose one of your locations.');
            }

            return;
        }

        if ((int) $locationId === $current) {
            return;
        }

        $location = app(LocationRepository::class)->findFor($workspace, (int) $locationId);

        if ($location && ! $access->usesLocation($location->id)) {
            $validator->errors()->add('location_id', "You do not have access to {$location->name}. Choose one of your locations.");
        } elseif ($location && ! $location->is_active) {
            $validator->errors()->add('location_id', "{$location->name} is inactive. Choose an active location.");
        }
    }
}
