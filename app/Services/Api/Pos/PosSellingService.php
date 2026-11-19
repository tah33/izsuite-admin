<?php

namespace App\Services\Api\Pos;

use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\ItemComponent;
use App\Models\User\PosSession;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\PosSessionRepository;
use App\Repositories\User\PosSettingRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Api\Sales\SalesDocumentService;
use App\Services\Shared\WorkspaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The POS selling screen (Doc 18 sections 18.7 to 18.14 - Phase C1), in the
 * user's own open session, at its register, in the register's currency.
 *
 * It starts with the register, the walk-in customer of Settings → Point of
 * Sale (while a sale can be made to them), the categories that hold items to
 * sell, and the most-sold items: those on the most issued invoices at the
 * register's location in the last 30 days.
 *
 * Items to sell are active and not for purchases only. A search finds them as
 * the Items list's does - by name, Item Number, SKU, barcode, category and,
 * while brands are on, brand - and says which one's barcode or SKU it is
 * exactly: the one a scanner typed, added at once.
 *
 * The cart - its items and quantities, for its customer - is priced, taxed and
 * totalled by PosPricing. Each of its items must still be sold, and a combo's
 * components switched on.
 */
class PosSellingService
{
    use RefusesConflicts;

    /** How many items a search shows. */
    public const FOUND = 24;

    /** How many most-sold items the screen shows, sold over how many days. */
    public const FREQUENT = 12;

    public const FREQUENT_DAYS = 30;

    public function __construct(
        protected PosSessionRepository $sessions,
        protected PosSettingRepository $settings,
        protected ItemRepository $items,
        protected ContactRepository $contacts,
        protected PosPricing $pricing,
        protected SalesDocumentService $salesDocuments,
        protected WorkspaceAccess $access,
    ) {}

    /**
     * What the screen starts with - or, while the user has no open session,
     * that alone. Without a rate for the register's currency today, its items
     * have no prices.
     *
     * @return array{session: ?PosSession, walk_in?: ?Contact, categories?: Collection, frequent?: list<array>, price_tax_mode?: string, rate_missing?: bool}
     */
    public function screen(Workspace $workspace): array
    {
        $session = $this->session($workspace);

        if (! $session) {
            return ['session' => null];
        }

        $today    = $this->salesDocuments->today($workspace);
        $rate     = $this->pricing->rate($workspace, $session->register, $today);
        $since    = CarbonImmutable::parse($today)->subDays(self::FREQUENT_DAYS)->toDateString();
        $frequent = $this->items->mostSoldAt($workspace, $session->register->location_id, $since, self::FREQUENT);
        $prices   = $rate === null ? [] : $this->pricing->standard($workspace, $session->register, $frequent, $rate, $today);

        return [
            'session'        => $session,
            'walk_in'        => $this->walkIn($workspace),
            'categories'     => $this->items->categoriesSold($workspace),
            'frequent'       => $frequent->map(fn (Item $item) => $this->row($item, $prices))->all(),
            'price_tax_mode' => $workspace->price_tax_mode,
            'rate_missing'   => $rate === null,
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
        $session = $this->selling($workspace);
        $today   = $this->salesDocuments->today($workspace);
        $rate    = $this->rate($workspace, $session, $today);
        $search  = trim((string) ($filters['search'] ?? '')) ?: null;
        $found   = $this->items->forSelling($workspace, $search, isset($filters['category_id']) ? (int) $filters['category_id'] : null, (bool) $workspace->brands_enabled, self::FOUND);
        $exact   = $search === null ? null : $this->items->byCode($workspace, $search);
        $prices  = $this->pricing->standard($workspace, $session->register, $exact ? $found->concat([$exact]) : $found, $rate, $today);

        return [
            'items' => $found->map(fn (Item $item) => $this->row($item, $prices))->all(),
            'exact' => $exact ? $this->row($exact, $prices) : null,
        ];
    }

    /**
     * $data is validated (see QuotePosCartRequest): the customer - none when
     * nobody is chosen - and the lines, each an item and its quantity. The
     * cart priced, taxed and totalled, its lines in order.
     */
    public function quote(Workspace $workspace, array $data): array
    {
        $session = $this->selling($workspace);
        $today   = $this->salesDocuments->today($workspace);
        $rate    = $this->rate($workspace, $session, $today);
        $contact = isset($data['contact_id']) ? $this->customer($workspace, (int) $data['contact_id']) : null;
        $asked   = array_values($data['lines']);
        $items   = $this->items->forCart($workspace, array_map(fn (array $line) => (int) $line['item_id'], $asked));
        $lines   = [];

        foreach ($asked as $index => $line) {
            $item = $items->get((int) $line['item_id']);

            $this->checkSold($item, $index);

            $lines[] = ['item' => $item, 'quantity' => bcadd((string) $line['quantity'], '0', 3)];
        }

        return $this->pricing->quote($workspace, $session->register, $contact, $lines, $rate, $today);
    }

    /** The user's open session, at a location they may still use - or none. */
    private function session(Workspace $workspace): ?PosSession
    {
        $session = $this->sessions->sellingOf($workspace, (int) $this->access->user()->id);

        return $session && $this->access->usesLocation($session->register->location_id) ? $session : null;
    }

    /** The user's open session: a sale is made in nothing else. */
    private function selling(Workspace $workspace): PosSession
    {
        return $this->session($workspace) ?? $this->refuse('Open your session first: you sell in a session of your own, at a register.');
    }

    /** Today's rate for the register's currency: nothing is priced without it. */
    private function rate(Workspace $workspace, PosSession $session, string $today): string
    {
        return $this->pricing->rate($workspace, $session->register, $today)
            ?? $this->refuse("There is no exchange rate for {$session->register->currency} today: add one in Settings → Currencies, and the register's prices follow.");
    }

    /** One of the business's active customers. */
    private function customer(Workspace $workspace, int $id): Contact
    {
        $contact = $this->contacts->findFor($workspace, $id);

        if (! $contact || ! $contact->is_active || ! $contact->isCustomer()) {
            throw ValidationException::withMessages(['contact_id' => 'Choose one of the business\'s active customers.']);
        }

        return $contact;
    }

    /** The walk-in customer of Settings → Point of Sale, while a sale can be made to them. */
    private function walkIn(Workspace $workspace): ?Contact
    {
        $id      = $this->settings->forWorkspace($workspace)->walk_in_contact_id;
        $contact = $id ? $this->contacts->findFor($workspace, $id) : null;

        return $contact && $contact->is_active && $contact->isCustomer() ? $contact : null;
    }

    /** That the $index-th cart line's item can be sold: the business's, active, not for purchases only, a combo's components switched on. */
    private function checkSold(?Item $item, int $index): void
    {
        $refusal = match (true) {
            ! $item                                 => 'This item is not one of the business\'s.',
            ! $item->is_active || ! $item->isSold() => "{$item->name} is no longer sold: take it out of the sale.",
            $item->isCombo() && $item->components->contains(fn (ItemComponent $row) => ! $row->component?->is_active) => "{$item->name} has a component that is switched off, so it is not sold.",
            default                                 => null,
        };

        if ($refusal !== null) {
            throw ValidationException::withMessages(["lines.{$index}.item_id" => $refusal]);
        }
    }

    /** $item with its price at the register, from $prices (PosPricing::standard()) - none when there is no rate. */
    private function row(Item $item, array $prices): array
    {
        return ['item' => $item, ...($prices[$item->id] ?? ['price' => null, 'source' => PosPricing::ITEM, 'price_list' => null])];
    }
}
