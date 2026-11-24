<?php

namespace App\Repositories\User;

use App\Models\User\Contact;
use App\Models\User\EcommerceCart;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\Item;
use App\Models\User\SalesDocument;
use App\Models\User\Storefront;
use App\Models\User\StorefrontCustomer;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Online orders (Doc 19 Phase D4): one placed from a cart - with its lines -
 * the order a cart became, the contact an earlier guest order made for an
 * e-mail, whether a number is taken, and whether a storefront, contact,
 * listing or item is on any order - and, from Phase E2, a customer
 * account's orders; from Phase F1, the business's Orders page and an
 * order's; from Phase F2, an order read again, locked, to be confirmed.
 */
class EcommerceOrderRepository
{
    /** Phase F2: an order's invoice as its customer is shown it - its number, and its secure link while one works. */
    private const CUSTOMER_INVOICE = ['invoice:id,number', 'invoice.activeLink'];

    /** Made through the storefront, so its business is never read from $data; its lines with it. */
    public function create(Storefront $storefront, array $data, array $lines): EcommerceOrder
    {
        $order = new EcommerceOrder(['storefront_id' => $storefront->id, ...$data]);
        $order->workspace_id = $storefront->workspace_id;
        $order->save();
        $order->lines()->createMany($lines);

        return $order->load('lines');
    }

    /** The order $cart became, if it did. */
    public function ofCart(EcommerceCart $cart): ?EcommerceOrder
    {
        return EcommerceOrder::query()->where('ecommerce_cart_id', $cart->id)->with('lines')->first();
    }

    /** $cart locked for the checkout, so it becomes one order at most. */
    public function lockCart(EcommerceCart $cart): EcommerceCart
    {
        return EcommerceCart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * The contact the latest guest order of $workspace made for $email - its
     * e-mail still that one, not merged away (else the one it was merged
     * into, when that has it) - or none.
     */
    public function contactMadeFor(Workspace $workspace, string $email): ?Contact
    {
        $order = EcommerceOrder::query()
            ->where('workspace_id', $workspace->id)
            ->where('contact_created', true)
            ->where('customer_email', mb_strtolower($email))
            ->latest('id')
            ->with('contact')
            ->first();

        $contact = $order?->contact ? $this->survivingContact($order->contact) : null;

        return $contact && $contact->is_active && mb_strtolower((string) $contact->email) === mb_strtolower($email) ? $contact : null;
    }

    /** $contact - or, merged since, the one it was merged into (Doc 17 Phase C3), as far as the merges go. */
    public function survivingContact(Contact $contact): Contact
    {
        while ($contact->merged_into_id && ($into = Contact::query()->find($contact->merged_into_id))) {
            $contact = $into;
        }

        return $contact;
    }

    /** Phase D5: $storefront's order whose own link's key hashes to $keyHash - with its lines, and (Phase F2) its invoice's working link - or none. */
    public function forCustomer(Storefront $storefront, string $keyHash): ?EcommerceOrder
    {
        return EcommerceOrder::query()
            ->where('storefront_id', $storefront->id)
            ->where('access_token_hash', $keyHash)
            ->with(['lines', ...self::CUSTOMER_INVOICE])
            ->first();
    }

    /** Phase E2: $customer's orders, the newest first, each with how many products it holds. */
    public function ofAccount(StorefrontCustomer $customer, int $perPage): LengthAwarePaginator
    {
        return EcommerceOrder::query()
            ->select(['id', 'number', 'placed_at', 'status', 'payment_status', 'fulfilment_status', 'currency', 'grand_total'])
            ->where('storefront_customer_id', $customer->id)
            ->withSum(['lines as products' => fn ($query) => $query->reorder()->where('kind', EcommerceOrderLine::PRODUCT)], 'quantity')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** Phase E2: $customer's order $id, with its lines, and (Phase F2) its invoice's working link - or none. */
    public function findOfAccount(StorefrontCustomer $customer, int $id): ?EcommerceOrder
    {
        return EcommerceOrder::query()->where('storefront_customer_id', $customer->id)->whereKey($id)->with(['lines', ...self::CUSTOMER_INVOICE])->first();
    }

    /** Phase E2: the orders of $customer's e-mail that are no account's - guest orders, or a closed account's - made its own. */
    public function adoptFor(StorefrontCustomer $customer): void
    {
        EcommerceOrder::query()
            ->where('workspace_id', $customer->workspace_id)
            ->whereNull('storefront_customer_id')
            ->where('customer_email', $customer->email)
            ->update(['storefront_customer_id' => $customer->id]);
    }

    /** Phase E2: $customer's orders let go - the account opened again, to be verified again. */
    public function releaseFrom(StorefrontCustomer $customer): void
    {
        EcommerceOrder::query()->where('storefront_customer_id', $customer->id)->update(['storefront_customer_id' => null]);
    }

    /**
     * Phase F1: the Orders page - the business's orders at $locationIds (any,
     * when null), the newest first, by storefront and each of the three
     * statuses, or a number, name or e-mail searched for - each with its
     * storefront and how many products it holds.
     */
    public function paginateFor(Workspace $workspace, ?array $locationIds, array $filters, int $perPage): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $this->at($workspace, $locationIds)
            ->select(['id', 'storefront_id', 'number', 'placed_at', 'status', 'payment_status', 'fulfilment_status', 'currency', 'grand_total', 'customer', 'delivery'])
            ->when($filters['storefront_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('storefront_id', (int) $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->when($filters['fulfilment_status'] ?? null, fn (Builder $query, string $status) => $query->where('fulfilment_status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', '%'.mb_strtolower($search).'%')
                ->orWhere('customer->name', 'like', "%{$search}%")))
            ->withSum(['lines as products' => fn ($query) => $query->reorder()->where('kind', EcommerceOrderLine::PRODUCT)], 'quantity')
            ->with('storefront:id,name')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** Phase F1: the business's order $id at $locationIds (any, when null), with what its page shows - from Phase F2 its invoice and who confirmed it - or none. */
    public function findFor(Workspace $workspace, int $id, ?array $locationIds): ?EcommerceOrder
    {
        return $this->at($workspace, $locationIds)
            ->with([
                'lines.priceList:id,name', 'storefront:id,name,code', 'location:id,name', 'contact:id,contact_number,name,is_active', 'customerAccount:id,email', 'emails',
                // Phase F3: what its invoice still owes, and the payments recorded against it.
                'invoice:id,number,document_date,balance_due', 'invoice.payments.account:id,name,currency', 'confirmer:id,first_name,last_name',
                // Phase F6a: its payment links, by whom each was sent.
                'paymentLinks.creator:id,first_name,last_name',
            ])
            ->find($id);
    }

    /** Phase F4: how many orders of the business $email placed from $from to $to, both included. */
    public function placedFromEmail(Workspace $workspace, string $email, \DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return EcommerceOrder::query()
            ->where('workspace_id', $workspace->id)
            ->where('customer_email', mb_strtolower($email))
            ->whereBetween('placed_at', [$from, $to])
            ->count();
    }

    /** Phase F3: the online order whose invoice $invoice is, if one is. */
    public function ofInvoice(SalesDocument $invoice): ?EcommerceOrder
    {
        return EcommerceOrder::query()->where('sales_document_id', $invoice->id)->first();
    }

    /**
     * Phase F2: $order read again to be confirmed - locked, when $lock, so it
     * is confirmed once - with its storefront, contact and customer's account
     * whole, as pricing and its invoice read them, its lines, and (Phase F3,
     * whose payments lock it so too) its invoice.
     */
    public function forConfirming(EcommerceOrder $order, bool $lock): EcommerceOrder
    {
        return EcommerceOrder::query()
            ->whereKey($order->id)
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->with(['storefront', 'contact', 'customerAccount', 'lines', 'invoice'])
            ->firstOrFail();
    }

    public function update(EcommerceOrder $order, array $data): void
    {
        $order->update($data);
    }

    /** Phase F5: one of an order's lines changed - its amounts worked out again. */
    public function updateLine(EcommerceOrderLine $line, array $data): void
    {
        $line->update($data);
    }

    /** Phase F5b: one of an order's products taken off it. */
    public function deleteLine(EcommerceOrderLine $line): void
    {
        $line->delete();
    }

    /** Phase F5b: $rows added to $order's products, after its last line. Its lines are read again after. */
    public function addLines(EcommerceOrder $order, array $rows): void
    {
        $position = (int) EcommerceOrderLine::query()->where('ecommerce_order_id', $order->id)->max('position');

        foreach ($rows as $row) {
            $order->lines()->create([...$row, 'position' => ++$position]);
        }

        $order->unsetRelation('lines');
    }

    /** Phase F5: $order's shipping line replaced by $row, last of its lines - or removed, with none. Its lines are read again after. */
    public function replaceShipping(EcommerceOrder $order, ?array $row): void
    {
        $lines = fn () => EcommerceOrderLine::query()->where('ecommerce_order_id', $order->id);

        $lines()->where('kind', EcommerceOrderLine::SHIPPING)->delete();

        if ($row !== null) {
            $order->lines()->create([...$row, 'kind' => EcommerceOrderLine::SHIPPING, 'position' => (int) $lines()->max('position') + 1]);
        }

        $order->unsetRelation('lines');
    }

    /** Phase D5: an e-mail kept with $order. */
    public function addEmail(EcommerceOrder $order, array $data): EcommerceOrderEmail
    {
        return $order->emails()->create($data);
    }

    public function findEmail(int $id): ?EcommerceOrderEmail
    {
        return EcommerceOrderEmail::query()->with(['order.lines', 'order.storefront', 'order.workspace', 'order.invoice:id,number'])->find($id);
    }

    public function updateEmail(EcommerceOrderEmail $email, array $data): void
    {
        $email->update($data);
    }

    public function numberTaken(Workspace $workspace, string $number): bool
    {
        return EcommerceOrder::query()->where('workspace_id', $workspace->id)->where('number', $number)->exists();
    }

    public function usesStorefront(Storefront $storefront): bool
    {
        return EcommerceOrder::query()->where('storefront_id', $storefront->id)->exists();
    }

    public function usesContact(Contact $contact): bool
    {
        return EcommerceOrder::query()->where('contact_id', $contact->id)->exists();
    }

    /** Whether any order's line is of the listing $productId. */
    public function usesListing(int $productId): bool
    {
        return EcommerceOrderLine::query()->where('catalog_product_id', $productId)->exists();
    }

    /** Whether any order's line is of $item - a listing's own or a variant's. */
    public function usesItem(Item $item): bool
    {
        return EcommerceOrderLine::query()->where('item_id', $item->id)->exists();
    }

    /** The business's orders, kept to those at $locationIds when given. */
    private function at(Workspace $workspace, ?array $locationIds): Builder
    {
        return EcommerceOrder::query()
            ->where('workspace_id', $workspace->id)
            ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('location_id', $locationIds));
    }
}
