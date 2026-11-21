<?php

namespace App\Repositories\User;

use App\Models\User\Contact;
use App\Models\User\EcommerceCart;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\Item;
use App\Models\User\Storefront;
use App\Models\User\StorefrontCustomer;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Online orders (Doc 19 Phase D4): one placed from a cart - with its lines -
 * the order a cart became, the contact an earlier guest order made for an
 * e-mail, whether a number is taken, and whether a storefront, contact,
 * listing or item is on any order - and, from Phase E2, a customer
 * account's orders.
 */
class EcommerceOrderRepository
{
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

        $contact = $order?->contact;

        while ($contact && $contact->merged_into_id) {
            $contact = Contact::query()->find($contact->merged_into_id);
        }

        return $contact && $contact->is_active && mb_strtolower((string) $contact->email) === mb_strtolower($email) ? $contact : null;
    }

    /** Phase D5: $storefront's order whose own link's key hashes to $keyHash - with its lines - or none. */
    public function forCustomer(Storefront $storefront, string $keyHash): ?EcommerceOrder
    {
        return EcommerceOrder::query()
            ->where('storefront_id', $storefront->id)
            ->where('access_token_hash', $keyHash)
            ->with('lines')
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

    /** Phase E2: $customer's order $id, with its lines - or none. */
    public function findOfAccount(StorefrontCustomer $customer, int $id): ?EcommerceOrder
    {
        return EcommerceOrder::query()->where('storefront_customer_id', $customer->id)->whereKey($id)->with('lines')->first();
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

    /** Phase D5: an e-mail kept with $order. */
    public function addEmail(EcommerceOrder $order, array $data): EcommerceOrderEmail
    {
        return $order->emails()->create($data);
    }

    public function findEmail(int $id): ?EcommerceOrderEmail
    {
        return EcommerceOrderEmail::query()->with(['order.lines', 'order.storefront', 'order.workspace'])->find($id);
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
}
