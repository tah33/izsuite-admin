<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceDiscount;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceDiscountRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A business's discount codes (Doc 19 section 19.31 - Phase H1), under
 * Manage Discounts: a page of them - each with its uses and where it stands
 * today - one added or changed, with its code kept in capitals and no other
 * of the business's alike, and its products the business's own; and one
 * deleted - or, once used on an order, deactivated, so its orders keep it.
 * Each is logged.
 */
class EcommerceDiscountService
{
    public function __construct(
        protected EcommerceDiscountRepository $discounts,
        protected BusinessCalendar $calendar,
    ) {}

    public function page(Workspace $workspace, ?string $search, int $perPage): LengthAwarePaginator
    {
        $page  = $this->discounts->paginateFor($workspace, trim((string) $search) ?: null, $perPage);
        $today = $this->today($workspace);

        foreach ($page->items() as $discount) {
            $discount->currentState = $discount->stateOn($today, (int) $discount->uses);
        }

        return $page;
    }

    /** $data as SaveEcommerceDiscountRequest takes it. */
    public function create(Workspace $workspace, User $user, array $data): EcommerceDiscount
    {
        [$fields, $products] = $this->fields($workspace, $data, null);

        $discount = DB::transaction(fn () => $this->discounts->create($workspace, [...$fields, 'created_by' => $user->id], $products));

        ActivityLogService::record('created', "Added the discount {$discount->name} ({$discount->code})", $discount, ['source' => 'api']);

        return $this->find($workspace, $discount->id);
    }

    public function update(Workspace $workspace, int $id, array $data): EcommerceDiscount
    {
        $discount = $this->find($workspace, $id);
        [$fields, $products] = $this->fields($workspace, $data, $discount->id);

        DB::transaction(fn () => $this->discounts->update($discount, $fields, $products));

        ActivityLogService::record('updated', "Changed the discount {$discount->name} ({$discount->code})", $discount, ['source' => 'api']);

        return $this->find($workspace, $id);
    }

    /** Deleted - or, used on an order, deactivated, its orders keeping it: whether it was deleted. */
    public function delete(Workspace $workspace, int $id): bool
    {
        $discount = $this->find($workspace, $id);

        if ($this->discounts->everUsed($discount)) {
            $this->discounts->update($discount, ['is_active' => false], $discount->products->pluck('id')->all());

            ActivityLogService::record('updated', "Deactivated the discount {$discount->name} ({$discount->code}), used on orders", $discount, ['source' => 'api']);

            return false;
        }

        $this->discounts->delete($discount);

        ActivityLogService::record('deleted', "Deleted the discount {$discount->name} ({$discount->code})", $discount, ['source' => 'api']);

        return true;
    }

    /** The business's listings a discount may be for, by title. */
    public function productChoices(Workspace $workspace, ?string $search): Collection
    {
        return $this->discounts->productChoices($workspace, trim((string) $search) ?: null);
    }

    public function find(Workspace $workspace, int $id): EcommerceDiscount
    {
        $discount = $this->discounts->findFor($workspace, $id) ?? abort(404, 'There is no such discount.');
        $discount->currentState = $discount->stateOn($this->today($workspace), (int) $discount->uses);

        return $discount;
    }

    /**
     * $data as it is kept, and the products it is for: its code in capitals,
     * no other discount of $workspace's alike; a value only where its kind
     * takes one; the products the business's own, and only for chosen ones.
     *
     * @return array{0: array, 1: list<int>}
     */
    private function fields(Workspace $workspace, array $data, ?int $except): array
    {
        $code = mb_strtoupper(trim($data['code']));

        if ($this->discounts->codeTaken($workspace, $code, $except)) {
            throw ValidationException::withMessages(['code' => 'Another of your discounts has this code: choose another.']);
        }

        $chosen   = $data['applies_to'] === EcommerceDiscount::PRODUCTS ? array_values(array_unique(array_map('intval', $data['product_ids'] ?? []))) : [];
        $products = $chosen === [] ? [] : $this->discounts->productIdsOf($workspace, $chosen);

        if (count($products) !== count($chosen)) {
            throw ValidationException::withMessages(['product_ids' => 'Choose products of your own catalog.']);
        }

        return [[
            'name'               => trim($data['name']),
            'code'               => $code,
            'kind'               => $data['kind'],
            'value'              => $data['kind'] === EcommerceDiscount::FREE_SHIPPING ? null : (string) $data['value'],
            'applies_to'         => $data['applies_to'],
            'minimum'            => isset($data['minimum']) && $data['minimum'] !== '' ? (string) $data['minimum'] : null,
            'starts_on'          => $data['starts_on'] ?? null,
            'ends_on'            => $data['ends_on'] ?? null,
            'usage_limit'        => $data['usage_limit'] ?? null,
            'per_customer_limit' => $data['per_customer_limit'] ?? null,
            'is_active'          => (bool) ($data['is_active'] ?? true),
        ], $products];
    }

    private function today(Workspace $workspace): string
    {
        return $this->calendar->today($workspace)->toDateString();
    }
}
