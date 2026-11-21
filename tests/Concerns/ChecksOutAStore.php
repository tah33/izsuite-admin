<?php

namespace Tests\Concerns;

use App\Models\User\Location;
use App\Models\User\PaymentAccount;

/**
 * A storefront ready for checkout (Doc 19 Phase D4), on RunsAStorefront's:
 * a zone, Bangladesh, with Standard ($5, 2-4 days) and Freight (quoted after
 * the order); pickup at Dhaka; and the payment methods Bank Transfer (its
 * reference optional), bKash (its reference required), Cash on Delivery and
 * Pay at the Shop - with what a cart and an order are made of.
 */
trait ChecksOutAStore
{
    /** The ids by name: the zone's methods, the pickup point and the payment methods. */
    protected array $ids = [];

    protected function setUpCheckout(): void
    {
        $zone = $this->postJson('/api/v1/business-settings/ecommerce/shipping-zones', ['name' => 'Bangladesh', 'countries' => ['BD']])->json('data.id');

        foreach (['Standard' => ['kind' => 'flat', 'price' => 5, 'days_min' => 2, 'days_max' => 4], 'Freight' => ['kind' => 'quotation']] as $name => $fields) {
            $this->ids[$name] = $this->postJson('/api/v1/business-settings/ecommerce/shipping-methods', ['shipping_zone_id' => $zone, 'name' => $name, ...$fields])->assertCreated()->json('data.id');
        }

        $dhaka = Location::where('code', 'DHK')->sole();
        $this->putJson('/api/v1/business-settings/ecommerce/pickup-points/'.$dhaka->id, ['is_active' => true, 'instructions' => 'Ask at the front desk.', 'ready_within' => 'Ready in 2 hours'])->assertOk();
        $this->ids['Dhaka'] = $dhaka->pickupPoint()->value('id');

        $bank = $this->business->paymentAccounts()->create(['name' => 'City Bank', 'type' => 'bank', 'currency' => 'USD']);
        $cash = $this->business->paymentAccounts()->create(['name' => 'Cash Box', 'type' => 'cash', 'currency' => 'USD']);

        foreach ([
            'Bank Transfer'    => ['type' => 'bank_transfer', 'payment_account_id' => $bank->id, 'instructions' => 'Pay into City Bank 0123456789.'],
            'bKash'            => ['type' => 'mobile_money', 'payment_account_id' => $cash->id, 'instructions' => 'Send to 01700000000.', 'reference' => 'required'],
            'Cash on Delivery' => ['type' => 'cash_on_delivery', 'payment_account_id' => $cash->id],
            'Pay at the Shop'  => ['type' => 'pay_on_pickup', 'payment_account_id' => $cash->id],
        ] as $name => $fields) {
            $this->ids[$name] = $this->postJson('/api/v1/business-settings/ecommerce/payment-methods', ['name' => $name, ...$fields])->assertCreated()->json('data.id');
        }
    }

    /** A new cart with $products (link => how many): its token. */
    protected function cartWith(array $products): string
    {
        $token = null;

        foreach ($products as $slug => $quantity) {
            $answer = $this->withHeaders($token ? ['X-Cart-Token' => $token] : [])->postJson(self::STORE.'/cart/lines', ['product' => $slug, 'quantity' => $quantity])->assertSuccessful();
            $token ??= $answer->json('data.token');
        }

        return $token;
    }

    protected function quote(string $token, array $choices = [])
    {
        return $this->withHeaders(['X-Cart-Token' => $token])->postJson(self::STORE.'/checkout/quote', $choices);
    }

    protected function place(string $token, array $fields)
    {
        return $this->withHeaders(['X-Cart-Token' => $token])->postJson(self::STORE.'/checkout', $fields);
    }

    /** An order shipped by Standard and paid by bank transfer, for $total: what checkout sends. */
    protected function order(string $total, array $fields = []): array
    {
        return [
            'customer'           => ['name' => 'Karim Rahman', 'email' => 'Karim@Example.test', 'phone' => '+8801700000001'],
            'billing'            => ['line1' => 'House 12, Road 5', 'city' => 'Dhaka', 'postal_code' => '1207', 'country' => 'BD'],
            'shipping_same'      => true,
            'delivery'           => 'shipping',
            'shipping_method_id' => $this->ids['Standard'],
            'payment_method_id'  => $this->ids['Bank Transfer'],
            'accept_terms'       => true,
            'expected_total'     => $total,
            ...$fields,
        ];
    }
}
