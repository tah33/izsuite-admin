<?php

namespace App\Services\Api\Locations;

use App\Models\User\Location;
use App\Models\User\Workspace;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\ExpenseRepository;
use App\Repositories\User\PurchaseRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Workspaces\PlanLimits;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * A business's locations (Doc 11 section 6, Doc 12 sections 16 to 19): added
 * as its plan allows, edited, made the Primary, turned off and on, and
 * deleted only while nothing uses them. A business's first location is its
 * Primary, and the Primary is never turned off or deleted - another is made
 * Primary first. An inactive location takes no new transactions but keeps
 * the ones it has.
 *
 * A user with Selected Locations sees, and changes, only those (Doc 12
 * section 20) - and adds none: a new one would be outside what they may use.
 * Each comes with how many users may use it.
 */
class LocationService
{
    use RefusesConflicts;

    public function __construct(
        protected LocationRepository $locationRepository,
        protected SalesDocumentRepository $salesDocumentRepository,
        protected PurchaseRepository $purchaseRepository,
        protected ExpenseRepository $expenseRepository,
        protected PaymentAccountRepository $paymentAccountRepository,
        protected PlanLimits $planLimits,
        protected WorkspaceAccess $access,
    ) {}

    public function list(Workspace $workspace): Collection
    {
        return $this->locationRepository->listFor($workspace, $this->access->locations());
    }

    /** Only as many as the business's plan gives it (see PlanLimits). The first is the Primary. */
    public function create(Workspace $workspace, array $data): Location
    {
        abort_if($this->access->locations() !== null, 403, 'Only a user with access to every location can add one.');

        $this->planLimits->ensureCanAddLocation($workspace);

        $location = $this->locationRepository->create($workspace, [...$data, 'is_primary' => ! $this->locationRepository->hasPrimary($workspace)]);

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer "POST api/v1/locations" entry for the same request.
        ActivityLogService::record('created', "Added location {$this->quoted($location)}", $location, ['source' => 'api']);

        return $this->listed($workspace, $location);
    }

    public function update(Workspace $workspace, int $id, array $data): Location
    {
        $location = $this->findOwned($workspace, $id);

        $this->locationRepository->update($location, $data);

        ActivityLogService::record('updated', "Updated location {$this->quoted($location)}", $location, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($location->getChanges(), ['updated_at' => true])),
        ]);

        return $this->listed($workspace, $location);
    }

    /** Turned on again: it takes new transactions. */
    public function activate(Workspace $workspace, int $id): Location
    {
        $location = $this->findOwned($workspace, $id);

        $this->locationRepository->update($location, ['is_active' => true]);

        ActivityLogService::record('updated', "Activated location {$this->quoted($location)}", $location, ['source' => 'api']);

        return $this->listed($workspace, $location);
    }

    /** Turned off: no new transactions, its own kept (Doc 12 section 19). Never the Primary. */
    public function deactivate(Workspace $workspace, int $id): Location
    {
        $location = $this->findOwned($workspace, $id);

        if ($location->is_primary) {
            $this->refuse('This is your Primary Location. Make another location the Primary before deactivating this one.');
        }

        $this->locationRepository->update($location, ['is_active' => false]);

        ActivityLogService::record('updated', "Deactivated location {$this->quoted($location)}", $location, ['source' => 'api']);

        return $this->listed($workspace, $location);
    }

    /** The business's Primary Location from now on - only an active one. */
    public function makePrimary(Workspace $workspace, int $id): Location
    {
        $location = $this->findOwned($workspace, $id);

        if (! $location->is_active) {
            $this->refuse('Activate this location before making it the Primary.');
        }

        $this->locationRepository->makePrimary($location);

        ActivityLogService::record('updated', "Made {$this->quoted($location)} the Primary Location", $location, ['source' => 'api']);

        return $this->listed($workspace, $location);
    }

    /**
     * Only an unused location can be deleted (Doc 12 section 19): one that
     * transactions or payment accounts use is deactivated instead. Never the
     * Primary.
     */
    public function delete(Workspace $workspace, int $id): void
    {
        $location = $this->findOwned($workspace, $id);

        if ($location->is_primary) {
            $this->refuse('This is your Primary Location. Make another location the Primary before deleting this one.');
        }

        if ($this->salesDocumentRepository->usesLocation($location)) {
            $this->refuse('Sales documents were made at this location, so it cannot be deleted. Deactivate it instead.');
        }

        if ($this->purchaseRepository->usesLocation($location)) {
            $this->refuse('Purchases were made at this location, so it cannot be deleted. Deactivate it instead.');
        }

        if ($this->expenseRepository->usesLocation($location)) {
            $this->refuse('Expenses were recorded at this location, so it cannot be deleted. Deactivate it instead.');
        }

        // An account of the location would quietly become the whole business's.
        if ($this->paymentAccountRepository->usesLocation($location)) {
            $this->refuse('Payment accounts belong to this location. Move them to another location, or to the whole business, first.');
        }

        $this->locationRepository->delete($location);

        ActivityLogService::record('deleted', "Deleted location {$this->quoted($location)}", $location, ['source' => 'api']);
    }

    /**
     * The name, in quotes, for an activity log description.
     *
     * activity_logs.description is a 255-character column and a location name
     * may be as long as 255 itself, so the name is cut down: left whole, a
     * valid long name would make the log insert fail after the location was
     * already saved, and the request would answer 500 for a write that worked.
     */
    private function quoted(Location $location): string
    {
        return '"'.Str::limit($location->name, 100).'"';
    }

    /** $location as the list has it: with how many users may use it. */
    private function listed(Workspace $workspace, Location $location): Location
    {
        return $this->locationRepository->listFor($workspace, [$location->id])->first() ?? $location;
    }

    /**
     * A location of another business - the owner's own other one, or someone
     * else's - answers exactly like one that does not exist, and so does one
     * the user may not use. Aborting with our own message also keeps Laravel's
     * default out of the response - "No query results for model
     * [App\Models\User\Location]" - which names an internal class.
     */
    private function findOwned(Workspace $workspace, int $id): Location
    {
        $location = $this->locationRepository->findFor($workspace, $id);

        return $location && $this->access->usesLocation($location->id) ? $location : abort(404, 'Location not found.');
    }
}
