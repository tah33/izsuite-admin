<?php

namespace App\Services\Api\Pos;

use App\Enums\Edition;
use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\PosDevice;
use App\Models\User\PosSession;
use App\Models\User\Workspace;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\PosDeviceRepository;
use App\Repositories\User\PosHeldOrderRepository;
use App\Repositories\User\PosOrderRepository;
use App\Repositories\User\PosSettingRepository;
use App\Services\User\Apps\WorkspaceApps;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The POS selling screen (Doc 18 sections 18.7 to 18.14 - Phase C1), in the
 * user's own open session, at its register, in the register's currency
 * (PosCounter).
 *
 * It starts with the register, the walk-in customer of Settings → Point of
 * Sale (while a sale can be made to them), the payment methods the register
 * takes (Phase C2), the categories that hold items to sell, the most-sold
 * items - those on the most issued invoices at the register's location in
 * the last 30 days - and the session's last sale (Phase C4).
 *
 * Items to sell are active and not for purchases only. A search finds them as
 * the Items list's does - by name, Item Number, SKU, barcode, category and,
 * while brands are on, brand - and says which one's barcode or SKU it is
 * exactly: the one a scanner typed, added at once.
 *
 * The cart - its items and quantities, for its customer - is priced, taxed and
 * totalled by PosPricing.
 */
class PosSellingService
{
    /** How many items a search shows. */
    public const FOUND = 24;

    /** How many most-sold items the screen shows, sold over how many days. */
    public const FREQUENT = 12;

    public const FREQUENT_DAYS = 30;

    public function __construct(
        protected PosCounter $counter,
        protected ItemRepository $items,
        protected PosPricing $pricing,
        protected PosDiscounts $discounts,
        protected PosSettingRepository $settings,
        protected PosOrderRepository $orders,
        protected PosHeldOrderRepository $held,
        protected PosCustomerCredit $credit,
        protected PosDeviceRepository $devices,
    ) {}

    /** $data is validated (see ShowPosCustomerCreditRequest): the customer. What credit of theirs can pay a sale at the user's register (Phase D3b). */
    public function credit(Workspace $workspace, array $data): string
    {
        $session = $this->counter->selling($workspace);

        return $this->credit->available($workspace, $this->counter->customer($workspace, (int) $data['contact_id']), $session->register->currency);
    }

    /**
     * What the screen starts with - or, while the user has no open session,
     * that alone. Without a rate for the register's currency today, its items
     * have no prices.
     *
     * @return array{session: ?PosSession, walk_in?: ?Contact, methods?: Collection, categories?: Collection, frequent?: list<array>, price_tax_mode?: string, rate_missing?: bool}
     */
    public function screen(Workspace $workspace): array
    {
        $session = $this->counter->session($workspace);

        if (! $session) {
            return ['session' => null];
        }

        $today    = $this->counter->today($workspace);
        $rate     = $this->pricing->rate($workspace, $session->register, $today);
        $since    = CarbonImmutable::parse($today)->subDays(self::FREQUENT_DAYS)->toDateString();
        $frequent = $this->items->mostSoldAt($workspace, $session->register->location_id, $since, self::FREQUENT);
        $prices   = $rate === null ? [] : $this->pricing->standard($workspace, $session->register, $frequent, $rate, $today);

        return [
            'session'        => $session,
            'walk_in'        => $this->counter->walkIn($workspace),
            'methods'        => $this->counter->methods($workspace, $session->register)->pluck('method')->values(),
            'categories'     => $this->items->categoriesSold($workspace),
            'frequent'       => $frequent->map(fn (Item $item) => $this->row($item, $prices))->all(),
            'price_tax_mode' => $workspace->price_tax_mode,
            'rate_missing'   => $rate === null,
            // Phase C3: the step a cash sale's total rounds to - none when null.
            'cash_rounding'  => $this->settings->forWorkspace($workspace)->cash_rounding,
            // Phase C4: the session's last sale, for its receipt.
            'last_order'     => $this->orders->lastIn($session),
            // Phase D1: how many orders wait at the register.
            'held_count'     => $this->held->countWaitingAt($session->register),
            // Phase D2: how many of the session's payments wait to be confirmed.
            'pending_count'  => $this->orders->countPendingIn($session),
            // Phase D3a: whether a sale can be left on a customer's account - with the full Sales app.
            'credit_sales'   => WorkspaceApps::for($workspace)->has('sales', Edition::Full),
            // Phase I1: the register's lock.
            'lock'           => $this->counter->lockOf($workspace, $session),
        ];
    }

    /**
     * $filters are validated (see SearchPosItemsRequest): the search and the
     * category. The items found, priced at the register - and the one whose
     * barcode or SKU the search is, exactly.
     *
     * @return array{items: list<array>, exact: ?array}
     */
    public function items(Workspace $workspace, array $filters): array
    {
        $session = $this->counter->selling($workspace);
        $today   = $this->counter->today($workspace);
        $rate    = $this->counter->rate($workspace, $session, $today);
        $search  = trim((string) ($filters['search'] ?? '')) ?: null;
        $found   = $this->items->forSelling($workspace, $search, isset($filters['category_id']) ? (int) $filters['category_id'] : null, (bool) $workspace->brands_enabled, self::FOUND);
        $exact   = $search === null ? null : $this->items->byCode($workspace, $search);

        // Doc 18 Phase I3: an item found by its very code was scanned - the register's scanners, used now.
        if ($exact) {
            $this->devices->used($session->pos_register_id, PosDevice::BARCODE_SCANNER);
        }
        $prices  = $this->pricing->standard($workspace, $session->register, $exact ? $found->concat([$exact]) : $found, $rate, $today);

        return [
            'items' => $found->map(fn (Item $item) => $this->row($item, $prices))->all(),
            'exact' => $exact ? $this->row($exact, $prices) : null,
        ];
    }

    /**
     * $data is validated (see QuotePosCartRequest): the customer - none when
     * nobody is chosen - the lines, each an item, its quantity and its
     * price typed over or discount (Phase C3), and the order's discount.
     * The cart priced, taxed and totalled, its lines in order - and whether
     * its discounts go beyond Settings' limit, with the limit.
     */
    public function quote(Workspace $workspace, array $data): array
    {
        $session  = $this->counter->selling($workspace);
        $today    = $this->counter->today($workspace);
        $rate     = $this->counter->rate($workspace, $session, $today);
        $contact  = isset($data['contact_id']) ? $this->counter->customer($workspace, (int) $data['contact_id']) : null;
        $lines    = $this->counter->lines($workspace, $data['lines']);
        $discount = $this->counter->discount($data);
        $priced   = $this->pricing->priced($workspace, $session->register, $contact, $lines, $rate, $today, $discount);
        $limits   = $this->discounts->check($workspace, $lines, $priced, $discount, false, $data['approvals'] ?? []);

        return [...$this->pricing->shown($priced), 'beyond_limit' => $limits['beyond'], 'limit' => $limits['limit']];
    }

    /** $item with its price at the register, from $prices (PosPricing::standard()) - none when there is no rate. */
    private function row(Item $item, array $prices): array
    {
        return ['item' => $item, ...($prices[$item->id] ?? ['price' => null, 'source' => PosPricing::ITEM, 'price_list' => null])];
    }
}
