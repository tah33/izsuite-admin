<?php

namespace App\Services\Api\Ecommerce;

use App\Enums\Edition;
use App\Models\User\Storefront;
use App\Models\User\Workspace;
use App\Repositories\User\BusinessSettingRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\PriceListRepository;
use App\Repositories\User\StorefrontRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use App\Services\User\Workspaces\PlanLimits;
use App\Support\SettingChanges;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A business's online storefronts (Doc 19 section 19.4 - Phase A1): the
 * Storefronts page - those whose location the user may use - and adding,
 * changing and deleting one.
 *
 * A storefront's name is its own in the business; its code, in small letters,
 * is its address - one no other storefront of izSuite has, and none izSuite
 * keeps for itself. It sells from one active location the user may use, in
 * the business's currencies, its default one among them; its languages are
 * among those izSuite offers, its default one among them; and it serves at
 * least one country. Each change is logged.
 *
 * Those not archived count against the plan's storefronts (Phase A2): one
 * added, or opened again from Archived, needs room under its limit.
 */
class StorefrontService
{
    public function __construct(
        protected StorefrontRepository $storefronts,
        protected LocationRepository $locations,
        protected WorkspaceAccess $access,
        protected PlanLimits $planLimits,
        protected BusinessSettingRepository $businessSettings,
        protected PriceListRepository $priceLists,
        protected EcommerceOrderRepository $orders,
    ) {}

    /** The storefronts whose location the user may use, by name. */
    public function index(Workspace $workspace): Collection
    {
        return $this->storefronts->getFor($workspace, $this->access->locations());
    }

    /**
     * What a storefront's form starts from that only the business knows: its
     * currencies, the base one first, and its own country - a new
     * storefront's first, when the business profile has one. Phase D3: with
     * full Sales, its active price lists - none without.
     */
    public function options(Workspace $workspace): array
    {
        return [
            'currencies'  => $workspace->currencyCodes(),
            'country'     => $this->businessSettings->findFor($workspace)?->country ?: null,
            'price_lists' => $this->sellsWithPriceLists($workspace) ? $this->priceLists->activeFor($workspace) : null,
        ];
    }

    /** $data is validated (see SaveStorefrontRequest). One not archived needs room under the plan (Phase A2). */
    public function create(Workspace $workspace, array $data): Storefront
    {
        $fields = $this->checked($workspace, $data);

        if ($fields['status'] !== Storefront::ARCHIVED) {
            $this->planLimits->ensureCanAddStorefront($workspace);
        }

        $storefront = $this->storefronts->create($workspace, $fields);

        ActivityLogService::record('created', 'Added the storefront '.$this->quoted($storefront), $storefront, ['source' => 'api']);

        return $storefront;
    }

    /** An archived one opened again needs room under the plan (Phase A2). */
    public function update(Workspace $workspace, int $id, array $data): Storefront
    {
        $storefront = $this->find($workspace, $id);
        $fields     = $this->checked($workspace, $data, $storefront);

        if ($storefront->status === Storefront::ARCHIVED && $fields['status'] !== Storefront::ARCHIVED) {
            $this->planLimits->ensureCanAddStorefront($workspace);
        }

        if ($this->storefronts->update($storefront, $fields)) {
            ActivityLogService::record('updated', 'Updated the storefront '.$this->quoted($storefront), $storefront, ['source' => 'api', 'changes' => SettingChanges::of($storefront)]);
        }

        return $storefront;
    }

    /** One with orders (Phase D4) is kept for them: it is archived instead. */
    public function delete(Workspace $workspace, int $id): void
    {
        $storefront = $this->find($workspace, $id);

        if ($this->orders->usesStorefront($storefront)) {
            throw new HttpResponseException(response()->json([
                'message' => $storefront->name.' has orders, so it cannot be deleted. Archive it instead.',
            ], 409));
        }

        $this->storefronts->delete($storefront);

        ActivityLogService::record('deleted', 'Deleted the storefront '.$this->quoted($storefront), $storefront, ['source' => 'api']);
    }

    private function find(Workspace $workspace, int $id): Storefront
    {
        return $this->storefronts->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'Storefront not found.');
    }

    /** $data as a storefront keeps it - each part checked as the class says. */
    private function checked(Workspace $workspace, array $data, ?Storefront $storefront = null): array
    {
        $name = trim($data['name']);
        $code = Str::lower(trim($data['code']));

        if ($this->storefronts->nameTaken($workspace, $name, $storefront?->id)) {
            throw ValidationException::withMessages(['name' => 'Another storefront has this name.']);
        }

        if (in_array($code, Storefront::RESERVED_CODES, true)) {
            throw ValidationException::withMessages(['code' => 'izSuite keeps this code for itself: choose another.']);
        }

        if ($this->storefronts->codeTaken($code, $storefront?->id)) {
            throw ValidationException::withMessages(['code' => 'Another storefront has this code: choose another.']);
        }

        $locationId = (int) $data['location_id'];

        if ($locationId !== $storefront?->location_id) {
            $location = $this->locations->findFor($workspace, $locationId);

            if (! $location || ! $location->is_active || ! $this->access->usesLocation($locationId)) {
                throw ValidationException::withMessages(['location_id' => 'Choose one of your locations that is switched on.']);
            }
        }

        $businessCurrencies = $workspace->currencyCodes();
        $currency           = $data['currency'];
        $currencies         = $this->withDefault($data['currencies'] ?? [], $currency);

        if (! in_array($currency, $businessCurrencies, true)) {
            throw ValidationException::withMessages(['currency' => "Choose one of the business's currencies."]);
        }

        if (array_diff($currencies, $businessCurrencies) !== []) {
            throw ValidationException::withMessages(['currencies' => "Choose among the business's currencies."]);
        }

        $priceListId = $this->priceList($workspace, $data, $storefront, $currency, $locationId);

        return [
            'name'             => $name,
            'code'             => $code,
            // Not sent, a storefront keeps its own - a new one starts as Draft.
            'status'           => $data['status'] ?? $storefront?->status ?? Storefront::DRAFT,
            'default_language' => $data['default_language'],
            'languages'        => $this->withDefault($data['languages'] ?? [], $data['default_language']),
            'currency'         => $currency,
            // The business's order: its base currency first.
            'currencies'       => array_values(array_intersect($businessCurrencies, $currencies)),
            'countries'        => array_values(array_unique($data['countries'])),
            'location_id'      => $locationId,
            'price_list_id'    => $priceListId,
            'support_email'    => ($data['support_email'] ?? null) ?: null,
            'support_phone'    => trim((string) ($data['support_phone'] ?? '')) ?: null,
        ];
    }

    /**
     * Phase D3: the price list it sells at - with full Sales, an active one
     * in its currency, for its location; none without. Not sent, as by a form
     * from before Phase D3, it keeps its own.
     */
    private function priceList(Workspace $workspace, array $data, ?Storefront $storefront, string $currency, int $locationId): ?int
    {
        if (! array_key_exists('price_list_id', $data)) {
            return $storefront?->price_list_id;
        }

        $id = $data['price_list_id'] ? (int) $data['price_list_id'] : null;

        if ($id === null) {
            return null;
        }

        $list = $this->sellsWithPriceLists($workspace) ? $this->priceLists->activeFor($workspace)->firstWhere('id', $id) : null;

        if (! $list || $list->currency !== $currency || (! $list->all_locations && ! $list->locations->contains('id', $locationId))) {
            throw ValidationException::withMessages(['price_list_id' => "Choose an active price list in the storefront's currency, for its location."]);
        }

        return $id;
    }

    private function sellsWithPriceLists(Workspace $workspace): bool
    {
        return WorkspaceApps::for($workspace)->has('sales', Edition::Full);
    }

    /** $values, once each, with $default among them - first, when it was not. */
    private function withDefault(array $values, string $default): array
    {
        $values = array_values(array_unique($values));

        return in_array($default, $values, true) ? $values : [$default, ...$values];
    }

    /** '"Main Shop" (main-shop)'. */
    private function quoted(Storefront $storefront): string
    {
        return '"'.Str::limit($storefront->name, 100).'" ('.$storefront->code.')';
    }
}
