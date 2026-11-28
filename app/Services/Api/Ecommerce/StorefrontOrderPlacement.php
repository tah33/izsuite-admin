<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceCart;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\EcommercePaymentMethod;
use App\Models\User\EcommerceSetting;
use App\Models\User\Storefront;
use App\Models\User\StorefrontCustomer;
use App\Repositories\User\EcommerceCartRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\EcommerceSettingRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Workspaces\WorkspaceSequence;
use App\Support\Countries;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An order placed from a cart (Doc 19 sections 19.15 to 19.20 - Phase D4),
 * by a guest or - from Phase E2 - a signed-in customer, on a Live store:
 *
 * - the cart read again: every line buyable, the delivery and payment
 *   chosen still offered (StorefrontCheckout), the shipping address in a
 *   country the store sells to, a payment reference given where the method
 *   asks for one, and the terms accepted;
 * - its total the one the customer reviewed: otherwise refused, to be
 *   reviewed again;
 * - its contact found or made (StorefrontCustomerContacts): signed in with
 *   a verified e-mail, the account's (Phase E2);
 * - signed in, the account's order, at the account's e-mail, its addresses
 *   kept on the account; with guest checkout off, only signed in;
 * - numbered from Settings → eCommerce's prefix (EC-00001);
 * - kept with its lines - the products and the shipping - and the cart
 *   converted, linked to it.
 *
 * A cart becomes one order at most: placing it again answers with the order
 * it became. Nothing is reserved or counted down until the order is
 * confirmed (Phase F). From Phase F4, an order that may want a look is held
 * as it is placed while Settings → eCommerce says so (EcommerceOrderHolds).
 */
class StorefrontOrderPlacement
{
    use RefusesConflicts;

    public function __construct(
        protected StorefrontCatalogService $stores,
        protected StorefrontCheckout $checkout,
        protected StorefrontPricing $pricing,
        protected EcommerceCartRepository $carts,
        protected EcommerceOrderRepository $orders,
        protected EcommerceSettingRepository $settings,
        protected StorefrontCustomerContacts $customers,
        protected WorkspaceSequence $sequence,
        protected StoreOrderMailer $mailer,
        protected StorefrontCustomerAddresses $addresses,
        protected EcommerceOrderHolds $holds,
        protected StorefrontCartService $cartService,
    ) {}

    /**
     * The visitor's cart at checkout, with $choices (StorefrontCheckout) - and
     * the store, Settings → eCommerce, its published policies, and whether it
     * takes orders now: its team previewing it may try the checkout before it
     * is Live, but not place an order. From Phase E3, whether its signed-in
     * customer takes marketing by e-mail already (null for a guest, or while
     * their e-mail is not verified). From Phase H3a the cart, used now, learns
     * its customer: the account, or the name and e-mail typed (customerOf()).
     *
     * @return array{quote: array, storefront: Storefront, settings: EcommerceSetting, policies: array, open: bool, signed_in: bool, marketing: ?bool}
     */
    public function review(string $code, ?string $token, array $choices, ?\App\Models\User\User $previewer = null, ?StorefrontCustomer $account = null): array
    {
        ['storefront' => $storefront, 'today' => $today, 'open' => $open] = $this->stores->openStore($code, $previewer);
        $cart = ($token ? $this->carts->findActive($storefront, hash('sha256', $token)) : null) ?? abort(404, 'Your cart has expired. Add the products again.');

        ActivityLogService::skipForThisRequest();

        $marketing = $account ? $this->customers->marketingOf($storefront, $account) : null;
        $this->cartService->atCheckout($storefront, $cart, $this->customerOf($account, $choices, $marketing));

        return [
            // Phase H2b: with the account its customer is signed in to - its e-mail theirs, as the order's is - for the discounts.
            'quote'      => $this->checkout->quote($storefront, $cart, [...$choices, 'account' => $account, ...($account ? ['email' => $account->email] : [])], $today),
            'storefront' => $storefront,
            'settings'   => $this->settings->forWorkspace($storefront->workspace),
            'policies'   => $this->stores->policiesOf($storefront),
            'open'       => $open,
            // Phase E2: whether its customer is signed in, so a store without guest checkout takes their order.
            'signed_in'  => $account !== null,
            'marketing'  => $marketing,
        ];
    }

    /**
     * $data as PlaceStoreOrderRequest takes it; $meta: the customer's
     * address (ip) and browser (agent); $account: the customer signed in, if
     * they are (Phase E2). The order and its own link's key (Phase D5) - the
     * same for a cart placed again.
     *
     * @return array{order: EcommerceOrder, key: string, created: bool}
     */
    public function place(string $code, string $token, array $data, array $meta, ?StorefrontCustomer $account = null): array
    {
        ['storefront' => $storefront, 'today' => $today] = $this->stores->openStore($code);
        $settings = $this->settings->forWorkspace($storefront->workspace);
        $cart     = $this->carts->findByToken($storefront, hash('sha256', $token)) ?? abort(404, 'Your cart has expired. Add the products again.');
        $key      = hash_hmac('sha256', 'order|'.$token, (string) config('app.key'));

        if (! $settings->guest_checkout && ! $account) {
            abort(403, 'Sign in to place your order: this store takes orders from its signed-in customers.');
        }

        // Signed in, the order is sent to the account's e-mail, and is the account's.
        if ($account) {
            $data['customer']['email'] = $account->email;
        }

        return DB::transaction(function () use ($storefront, $today, $settings, $cart, $data, $meta, $key, $account): array {
            $cart = $this->orders->lockCart($cart);

            if ($cart->status === EcommerceCart::CONVERTED) {
                return ['order' => $this->orders->ofCart($cart) ?? abort(404, 'Your cart has expired. Add the products again.'), 'key' => $key, 'created' => false];
            }

            // Phase H3a: an abandoned cart is placed as an active one is.
            if (! in_array($cart->status, EcommerceCart::OPEN, true) || $cart->expires_at?->isPast()) {
                abort(404, 'Your cart has expired. Add the products again.');
            }

            $cart->load(['lines', 'lines.product:id,title,slug,options', 'lines.variant:id,option_values', 'lines.item:id,name']);
            $quote = $this->checkout->quote($storefront, $cart, $this->choices($data, $account), $today);

            $this->check($storefront, $settings, $quote, $data);

            $number = $this->number($storefront);
            ['contact' => $contact, 'created' => $created] = $account?->isVerified()
                ? $this->customers->linkAccount($storefront, $account)
                : $this->customers->contactFor($storefront, $data, $number);

            $order = $this->orders->create($storefront, [
                'ecommerce_cart_id'  => $cart->id,
                'number'             => $number,
                'contact_id'         => $contact->id,
                'contact_created'    => $created,
                'storefront_customer_id' => $account?->id,
                'location_id'        => $storefront->location_id,
                'status'             => EcommerceOrder::PENDING,
                'payment_status'     => trim((string) ($data['payment_reference'] ?? '')) !== '' ? EcommerceOrder::PAYMENT_PENDING : EcommerceOrder::UNPAID,
                // Phase G3a: its digital products and services are delivered too, so even an order with nothing to ship is to be fulfilled.
                'fulfilment_status'  => EcommerceOrder::UNFULFILLED,
                'currency'           => $quote['terms']['currency'],
                'exchange_rate'      => $quote['terms']['rate'],
                'price_tax_mode'     => $storefront->workspace->price_tax_mode,
                'tax_rounding'       => $storefront->workspace->tax_rounding,
                'prices_include_tax' => $quote['terms']['with_tax'],
                'subtotal'           => $quote['shown']['subtotal'],
                'shipping_total'     => $quote['shown']['shipping'],
                // As the store showed it, as an order's discount is kept (Phase F5b).
                'discount_total'     => $quote['shown']['discount'],
                'tax_total'          => $quote['totals']['tax_total'],
                'grand_total'        => $quote['totals']['grand_total'],
                'net_total'          => $quote['totals']['net_total'],
                'base_grand_total'   => $quote['totals']['base_grand_total'],
                'taxes'              => $quote['totals']['taxes'],
                ...$this->customer($data, $quote, $settings),
                ...$this->delivery($quote),
                ...$this->payment($quote['payment'], $data),
                ...$this->promotion($quote),
                'customer_note'      => $settings->checkout_notes ? (trim((string) ($data['note'] ?? '')) ?: null) : null,
                'source'             => 'storefront',
                'campaign'           => array_filter($data['campaign'] ?? []) ?: null,
                'consents'           => ['terms' => ['accepted_at' => now()->toIso8601String()], 'marketing' => (bool) ($data['marketing'] ?? false)],
                'ip_address'         => $meta['ip'] ?? null,
                'user_agent'         => isset($meta['agent']) ? mb_substr($meta['agent'], 0, 255) : null,
                'access_token_hash'  => hash('sha256', $key),
                'placed_at'          => now(),
            ], $this->lines($quote), $this->promotions($quote));

            $this->carts->update($cart, ['status' => EcommerceCart::CONVERTED, 'converted_at' => now()]);

            if ($account) {
                $this->addresses->keepFromOrder($account, $order->billing_address, $order->shipping_address);
            }

            if ($data['marketing'] ?? false) {
                $this->customers->recordConsent($storefront, $contact, $order);
            }

            ActivityLogService::recordCustomer($storefront->workspace, 'created', "Online order {$order->number} placed on {$storefront->name} by {$order->customer['name']}"
                .($quote['discounts'] === [] ? '' : ', with '.implode(', ', array_map(fn (array $one) => $one['discount']->code ? "code {$one['discount']->code}" : $one['discount']->name, $quote['discounts']))), $order);

            // Phase F4: held as it is placed, with Hold Orders for Review on, when something wants a look.
            $this->holds->holdAsPlaced($storefront->workspace, $order, $settings);

            // Phase D5: the order received, e-mailed once the order is saved, with its own page's link.
            $this->mailer->queue($order, EcommerceOrderEmail::ORDER_RECEIVED, self::linkOf($storefront, $key));

            return ['order' => $order, 'key' => $key, 'created' => true];
        });
    }

    /** Phase D5: an order's own page, by its key: at the store's address on izSuite's frontend. */
    public static function linkOf(Storefront $storefront, string $key): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/store/'.$storefront->code.'/orders/'.$key;
    }

    /**
     * Phase H3a: who checks the cart out, as far as checkout knows - its
     * fields to keep. Signed in, the account: its name and e-mail, and
     * whether they agreed to marketing by e-mail - $standing, their
     * contact's, or the box ticked now. Else the name and a whole e-mail
     * typed, the box going with the e-mail: a field left empty keeps what the
     * cart knew.
     */
    private function customerOf(?StorefrontCustomer $account, array $choices, ?bool $standing): array
    {
        $ticked = (bool) ($choices['marketing'] ?? false);

        if ($account) {
            return ['storefront_customer_id' => $account->id, 'customer_name' => $account->name, 'customer_email' => $account->email, 'marketing' => $standing === true || $ticked];
        }

        $name  = mb_substr(trim((string) ($choices['name'] ?? '')), 0, 255);
        $email = mb_strtolower(trim((string) ($choices['email'] ?? '')));
        $whole = mb_strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

        return [
            ...($name !== '' ? ['customer_name' => $name] : []),
            ...($whole ? ['storefront_customer_id' => null, 'customer_email' => $email, 'marketing' => $ticked] : []),
        ];
    }

    /**
     * What the checkout is asked to work out from $data: the address it
     * delivers to, the delivery and the payment chosen - and the discounts,
     * with (Phase H2b) $account, the one its customer is signed in to.
     */
    private function choices(array $data, ?StorefrontCustomer $account): array
    {
        $delivery = $data['delivery'] ?? null;
        $address  = $delivery === EcommerceOrder::SHIPPING && ! ($data['shipping_same'] ?? true) ? ($data['shipping'] ?? []) : ($data['billing'] ?? []);

        return [
            'address'            => $address,
            'billing_country'    => $data['billing']['country'] ?? null,
            'delivery'           => $delivery,
            'shipping_method_id' => $data['shipping_method_id'] ?? null,
            'pickup_point_id'    => $data['pickup_point_id'] ?? null,
            'payment_method_id'  => $data['payment_method_id'] ?? null,
            // Phases H1 and H2a: a discount code and the automatic discounts, checked with the customer's e-mail - locked, so their uses are counted once.
            'discount_code'      => $data['discount_code'] ?? null,
            'email'              => $data['customer']['email'] ?? null,
            'account'            => $account,
            'lock_discount'      => true,
        ];
    }

    /**
     * Phases H1 and H2a: the order's discount from the discounts it takes
     * (F5b's way: an amount, its why naming them); none when none took
     * anything off.
     */
    private function promotion(array $quote): array
    {
        if ($quote['discounts'] === []) {
            return [];
        }

        $why = array_map(fn (array $one) => $one['discount']->code ? "Discount code {$one['discount']->code} ({$one['discount']->name})" : $one['discount']->name, $quote['discounts']);

        return [
            'discount_type'   => EcommerceOrder::AMOUNT,
            'discount_value'  => $quote['off'],
            'discount_reason' => mb_substr(implode('; ', $why), 0, 500),
        ];
    }

    /** Phases H1 and H2a: each discount the order takes, as it is now - kept with it - and what it takes off. */
    private function promotions(array $quote): array
    {
        return array_map(fn (array $one) => [
            'ecommerce_discount_id' => $one['discount']->id,
            'name'                  => $one['discount']->name,
            'code'                  => $one['discount']->code,
            'kind'                  => $one['discount']->kind,
            'value'                 => $one['discount']->value === null ? null : (string) $one['discount']->value,
            'off'                   => $one['off'],
        ], $quote['discounts']);
    }

    /** What the order needs before it is placed (19.20), each refused under its field. */
    private function check(Storefront $storefront, EcommerceSetting $settings, array $quote, array $data): void
    {
        if (! $quote['read']['ready']) {
            $this->refuse('Your cart has changed: review it, then check out again.');
        }

        // Phase H1: a code given is applied, or refused under its field.
        if ($quote['discount'] !== null && $quote['discount']['problem'] !== null) {
            throw ValidationException::withMessages(['discount_code' => $quote['discount']['problem']]);
        }

        if ($settings->checkout_phone === 'required' && trim((string) ($data['customer']['phone'] ?? '')) === '') {
            throw ValidationException::withMessages(['customer.phone' => 'Give a phone number we can reach you at.']);
        }

        if ($quote['needs_delivery']) {
            if ($quote['delivery'] === null) {
                throw ValidationException::withMessages(['delivery' => 'Choose delivery or pickup.']);
            }

            if ($quote['delivery'] === EcommerceOrder::SHIPPING && ! $quote['sells_to']) {
                throw ValidationException::withMessages([($data['shipping_same'] ?? true) ? 'billing.country' : 'shipping.country' => $storefront->name.' does not deliver to '.(Countries::name($quote['country']) ?? 'this country').'.']);
            }

            if ($quote['delivery'] === EcommerceOrder::SHIPPING && ! $quote['method']) {
                throw ValidationException::withMessages(['shipping_method_id' => 'Choose one of the delivery options offered for this address.']);
            }

            if ($quote['delivery'] === EcommerceOrder::PICKUP && ! $quote['point']) {
                throw ValidationException::withMessages(['pickup_point_id' => 'Choose where to collect your order.']);
            }
        }

        if (! $quote['payment']) {
            throw ValidationException::withMessages(['payment_method_id' => 'Choose one of the ways to pay offered for this order.']);
        }

        if ($quote['payment']->reference === 'required' && trim((string) ($data['payment_reference'] ?? '')) === '') {
            throw ValidationException::withMessages(['payment_reference' => "Give your payment's reference (its transaction ID)."]);
        }

        if (bccomp((string) $data['expected_total'], $quote['totals']['grand_total'], 2) !== 0) {
            $this->refuse("The total is now {$quote['terms']['currency']} {$quote['totals']['grand_total']}: review your order, then place it again.");
        }
    }

    /** The next number after Settings → eCommerce's prefix, five digits - one no order of the business has. */
    private function number(Storefront $storefront): string
    {
        $prefix = $this->settings->forWorkspace($storefront->workspace)->order_prefix ?: 'EC-';

        do {
            $number = $prefix.sprintf('%05d', $this->sequence->next($storefront->workspace_id, EcommerceOrder::NUMBER_SEQUENCE));
        } while ($this->orders->numberTaken($storefront->workspace, $number));

        return $number;
    }

    /** The customer's details and addresses as given: the shipping address only when it is shipped. */
    private function customer(array $data, array $quote, EcommerceSetting $settings): array
    {
        $customer = $data['customer'];
        $billing  = $this->address($data['billing']);

        return [
            'customer'         => ['name' => trim($customer['name']), 'email' => trim($customer['email']), 'phone' => $settings->checkout_phone === 'hidden' ? null : (trim((string) ($customer['phone'] ?? '')) ?: null), 'company' => trim((string) ($customer['company'] ?? '')) ?: null],
            'customer_email'   => mb_strtolower(trim($customer['email'])),
            'billing_address'  => $billing,
            'shipping_address' => $quote['delivery'] === EcommerceOrder::SHIPPING ? (($data['shipping_same'] ?? true) ? $billing : $this->address($data['shipping'])) : null,
        ];
    }

    private function address(array $address): array
    {
        return array_map(fn ($value) => trim((string) $value) ?: null, [
            'line1' => $address['line1'] ?? null, 'line2' => $address['line2'] ?? null, 'city' => $address['city'] ?? null,
            'region' => $address['region'] ?? null, 'postal_code' => $address['postal_code'] ?? null, 'country' => strtoupper((string) ($address['country'] ?? '')),
        ]);
    }

    /** How it is delivered, kept as it was: the method's name, price and days - quoted after the order or not - or the pickup point's. */
    public function delivery(array $quote): array
    {
        $method = $quote['method'];
        $point  = $quote['point'];

        return [
            'delivery'           => $quote['delivery'] ?? EcommerceOrder::NONE,
            'shipping_method_id' => $method['method']->id ?? null,
            'pickup_point_id'    => $point?->id,
            'delivery_snapshot'  => match (true) {
                $method !== null => ['name' => $method['method']->name, 'method_kind' => $method['method']->kind, 'price' => $method['shown'], 'quoted' => $method['price'] === null, 'days_min' => $method['method']->days_min, 'days_max' => $method['method']->days_max],
                $point !== null  => ['name' => $point->location->name, 'instructions' => $point->instructions, 'ready_within' => $point->ready_within, 'address' => $point->location->only(['address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country']), 'phone' => $point->location->phone],
                default          => null,
            },
        ];
    }

    /** How it is paid, kept as it was, with the customer's reference when its method takes one. */
    private function payment(EcommercePaymentMethod $method, array $data): array
    {
        return [
            'ecommerce_payment_method_id' => $method->id,
            'payment_snapshot'            => ['name' => $method->name, 'type' => $method->type, 'instructions' => $method->instructions, 'payment_account_id' => $method->payment_account_id],
            'payment_reference'           => $method->reference !== 'none' ? (trim((string) ($data['payment_reference'] ?? '')) ?: null) : null,
        ];
    }

    /** The order's lines: its products as they were bought, then its shipping. */
    private function lines(array $quote): array
    {
        $byLine = collect($quote['read']['lines'])->filter(fn (array $line) => $line['problem'] === null)->values();

        return array_map(function (array $row, int $at) use ($byLine, $quote) {
            $shipping = ($row['kind'] ?? 'product') === 'shipping';
            $line     = $shipping ? null : $byLine[$at];
            // As it shows before any discount (Phase H1): the amount itself carries its share of the discount.
            $shown    = $row['shown'] ?? $this->pricing->shownAmount($row, $quote['terms']);

            return [
                'kind'                       => $shipping ? EcommerceOrderLine::SHIPPING : EcommerceOrderLine::PRODUCT,
                'catalog_product_id'         => $line['product']->id ?? null,
                'catalog_product_variant_id' => $line['variant']->id ?? null,
                'item_id'                    => $row['item_id'],
                'title'                      => $shipping ? $row['name'] : $line['product']->title,
                'options'                    => $line && $line['variant'] ? array_values(array_map(fn ($value, $i) => ['name' => $line['product']->options[$i]['name'] ?? '', 'value' => $value], $line['variant']->option_values, array_keys($line['variant']->option_values))) : null,
                'sku'                        => $line['item']->sku ?? null,
                'name'                       => $row['name'],
                'unit'                       => $row['unit'],
                'quantity'                   => $row['quantity'],
                'unit_price'                 => $row['unit_price'],
                'price_source'               => $shipping ? null : $quote['read']['prices'][$at]['source'],
                'price_list_id'              => $row['price_list_id'],
                'price_list_rule_id'         => $row['price_list_rule_id'],
                'tax_rate_id'                => $row['tax_rate_id'],
                'tax_name'                   => $row['tax_name'],
                'tax_code'                   => $row['tax_code'],
                'tax_rate'                   => $row['tax_rate'],
                'tax_treatment'              => $row['tax_treatment'],
                'amount'                     => $row['amount'],
                'tax_amount'                 => $row['tax_amount'],
                'shown_unit_price'           => $shipping ? $shown : $line['unit'],
                'shown_amount'               => $shown,
                'weight'                     => $line ? $this->checkout->weightOf($line) : null,
                'shipped'                    => ! $shipping && $this->checkout->ships($line['item']),
                'position'                   => $at + 1,
            ];
        }, $quote['rows'], array_keys($quote['rows']));
    }
}
