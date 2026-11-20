<?php

namespace App\Services\Api\Pos;

use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\ItemComponent;
use App\Models\User\PosPaymentMethod;
use App\Models\User\PosRegister;
use App\Models\User\PosSession;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\PosPinRepository;
use App\Repositories\User\PosSessionRepository;
use App\Repositories\User\PosSettingRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Api\Sales\SalesDocumentService;
use App\Services\Shared\WorkspaceAccess;
use App\Support\Decimal;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * What selling at a POS register stands on (Doc 18 Phases C1 and C2): the
 * user's own open session, at a location they may still use; today's rate
 * for its register's currency; the customer - one of the business's active
 * ones, the walk-in one of Settings → Point of Sale to start; a cart's
 * items, each still sold; and the payment methods the register takes now.
 * Phase I1: nothing is sold at a locked register.
 */
class PosCounter
{
    use RefusesConflicts;

    public function __construct(
        protected PosSessionRepository $sessions,
        protected PosSettingRepository $settings,
        protected ItemRepository $items,
        protected ContactRepository $contacts,
        protected PaymentAccountRepository $accounts,
        protected PosPricing $pricing,
        protected SalesDocumentService $salesDocuments,
        protected WorkspaceAccess $access,
        protected PosPinRepository $pins,
    ) {}

    /** The user's open session, at a location they may still use - or none. */
    public function session(Workspace $workspace): ?PosSession
    {
        $session = $this->sessions->sellingOf($workspace, (int) $this->access->user()->id);

        return $session && $this->access->usesLocation($session->register->location_id) ? $session : null;
    }

    /** The user's open session: a sale is made in nothing else - nor while its register is locked (Phase I1). */
    public function selling(Workspace $workspace): PosSession
    {
        $session = $this->session($workspace) ?? $this->refuse('Open your session first: you sell in a session of your own, at a register.');

        $lock = $this->lockOf($workspace, $session);

        if ($lock['locked']) {
            $with = $lock['has_pin'] ? 'your PIN' : 'your password';

            throw new HttpResponseException(response()->json(['message' => "{$session->register->name} ({$session->register->code}) is locked: enter {$with} to go on."], 423));
        }

        // Used now: its idle minutes start again - kept at most twice a minute.
        if (! $session->last_active_at || $session->last_active_at->lt(now()->subSeconds(30))) {
            $this->sessions->mark($session, ['last_active_at' => now()]);
        }

        return $session;
    }

    /**
     * Its register's lock (Phase I1): locked by its cashier, or now - when
     * Settings → Point of Sale's minutes have gone by since it was last used;
     * those minutes, and whether the cashier has a PIN to open it with.
     *
     * @return array{locked: bool, minutes: ?int, has_pin: bool}
     */
    public function lockOf(Workspace $workspace, PosSession $session): array
    {
        $minutes = $this->settings->forWorkspace($workspace)->lock_minutes;

        if (! $session->locked_at && $minutes && $session->last_active_at?->lt(now()->subMinutes($minutes))) {
            $this->sessions->mark($session, ['locked_at' => now()]);
        }

        return ['locked' => $session->locked_at !== null, 'minutes' => $minutes, 'has_pin' => $this->pins->pinOf($this->access->user()) !== null];
    }

    public function today(Workspace $workspace): string
    {
        return $this->salesDocuments->today($workspace);
    }

    /** Today's rate for the register's currency: nothing is priced without it. */
    public function rate(Workspace $workspace, PosSession $session, string $today): string
    {
        return $this->pricing->rate($workspace, $session->register, $today)
            ?? $this->refuse("There is no exchange rate for {$session->register->currency} today: add one in Settings → Currencies, and the register's prices follow.");
    }

    /** One of the business's active customers. */
    public function customer(Workspace $workspace, int $id): Contact
    {
        return $this->usableCustomer($workspace, $id)
            ?? throw ValidationException::withMessages(['contact_id' => 'Choose one of the business\'s active customers.']);
    }

    /** The contact, while a sale can be made to them - one of the business's active customers - or null (Phase D1). */
    public function usableCustomer(Workspace $workspace, int $id): ?Contact
    {
        $contact = $this->contacts->findFor($workspace, $id);

        return $contact && $contact->is_active && $contact->isCustomer() ? $contact : null;
    }

    /** The walk-in customer of Settings → Point of Sale, while a sale can be made to them. */
    public function walkIn(Workspace $workspace): ?Contact
    {
        $id      = $this->settings->forWorkspace($workspace)->walk_in_contact_id;
        $contact = $id ? $this->contacts->findFor($workspace, $id) : null;

        return $contact && $contact->is_active && $contact->isCustomer() ? $contact : null;
    }

    /**
     * A cart's lines - each its item_id, quantity and, optionally, a note,
     * a price typed over the item's with why (Phase C3), and a discount -
     * with their items, each still sold: the business's, active, not for
     * purchases only, and a combo's components switched on.
     *
     * @return list<array{item: Item, quantity: string, note: ?string, price: ?string, override_reason: ?string, discount_type: ?string, discount_value: ?string}>
     */
    public function lines(Workspace $workspace, array $lines): array
    {
        $asked = array_values($lines);
        $items = $this->items->forCart($workspace, array_map(fn (array $line) => (int) $line['item_id'], $asked));
        $cart  = [];

        foreach ($asked as $index => $line) {
            $item    = $items->get((int) $line['item_id']);
            $refusal = $this->unsold($item);

            if ($refusal !== null) {
                throw ValidationException::withMessages(["lines.{$index}.item_id" => $refusal]);
            }

            $cart[] = [
                'item'            => $item,
                'quantity'        => bcadd((string) $line['quantity'], '0', 3),
                'note'            => trim((string) ($line['note'] ?? '')) ?: null,
                ...$this->adjusted($line, "lines.{$index}.discount_value"),
            ];
        }

        return $cart;
    }

    /**
     * Why $item cannot be sold - not the business's, switched off, for
     * purchases only, or a combo with a component switched off - or null
     * when it can. $item is one of ItemRepository::forCart()'s.
     */
    public function unsold(?Item $item): ?string
    {
        return match (true) {
            ! $item                                 => 'This item is not one of the business\'s.',
            ! $item->is_active || ! $item->isSold() => "{$item->name} is no longer sold: take it out of the sale.",
            $item->isCombo() && $item->components->contains(fn (ItemComponent $row) => ! $row->component?->is_active) => "{$item->name} has a component that is switched off, so it is not sold.",
            default                                 => null,
        };
    }

    /** The order's own discount (Phase C3) - its type and value - when it takes something off; else none. */
    public function discount(array $data): ?array
    {
        $taken = $this->taken($data['discount_type'] ?? null, $data['discount_value'] ?? null, 'discount_value');

        return $taken ? ['type' => $taken[0], 'value' => $taken[1]] : null;
    }

    /** A discount's type and value, when it takes something off - a percentage up to 100. */
    private function taken(?string $type, mixed $value, string $field): ?array
    {
        if ($type === null || $value === null || $value === '' || bccomp((string) $value, '0', 4) <= 0) {
            return null;
        }

        if ($type === 'percent' && bccomp((string) $value, '100', 4) > 0) {
            throw ValidationException::withMessages([$field => 'A percentage takes up to 100 off.']);
        }

        return [$type, bcadd((string) $value, '0', 4)];
    }

    /**
     * A line's adjustments (Phase C3): a price typed over the item's, and
     * why; a discount - a percentage or an amount - kept only when it takes
     * something off.
     *
     * @return array{price: ?string, override_reason: ?string, discount_type: ?string, discount_value: ?string}
     */
    private function adjusted(array $line, string $field): array
    {
        $price    = isset($line['price']) && $line['price'] !== '' ? Decimal::round((string) $line['price']) : null;
        $discount = $this->taken($line['discount_type'] ?? null, $line['discount_value'] ?? null, $field);

        return [
            'price'           => $price,
            'override_reason' => $price === null ? null : (trim((string) ($line['override_reason'] ?? '')) ?: null),
            'discount_type'   => $discount[0] ?? null,
            'discount_value'  => $discount[1] ?? null,
        ];
    }

    /**
     * The payment methods the register takes now, in their order, keyed by
     * id, each with the account its money goes into: switched on, into an
     * active account of the business taking customer payments, in the
     * register's currency - a cash method's into the register's own cash
     * account, when it has one. Expects the register's methods loaded.
     *
     * @return Collection<int, array{method: PosPaymentMethod, account_id: int}>
     */
    public function methods(Workspace $workspace, PosRegister $register): Collection
    {
        $accounts = $this->accounts->takingCustomerPayments($workspace)->keyBy('id');

        return $register->paymentMethods
            ->map(function (PosPaymentMethod $method) use ($register, $accounts): ?array {
                $into = $accounts->get($method->type === PosPaymentMethod::CASH && $register->cash_account_id ? $register->cash_account_id : (int) $method->payment_account_id);

                return $method->is_active && $into && $into->currency === $register->currency ? ['method' => $method, 'account_id' => (int) $into->id] : null;
            })
            ->filter()
            ->keyBy(fn (array $row) => $row['method']->id);
    }
}
