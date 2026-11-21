<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\PaymentAccount;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use App\Services\Api\Ecommerce\EcommercePaymentOptions;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase D2: the storefronts' payment methods without a gateway
 * (section 19.22) in Settings → eCommerce, under Manage Payment Methods -
 * bank transfer, mobile money, cash on delivery and pay on pickup, each into
 * a payment account - and which of them an order is offered
 * (EcommercePaymentOptions).
 */
class EcommercePaymentMethodsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const METHODS = '/api/v1/business-settings/ecommerce/payment-methods';

    private PaymentAccount $bank;

    private PaymentAccount $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00']]);
        $this->bank = $this->business->paymentAccounts()->create(['name' => 'City Bank', 'type' => 'bank', 'currency' => 'USD']);
        $this->cash = $this->business->paymentAccounts()->create(['name' => 'Cash Box', 'type' => 'cash', 'currency' => 'USD']);
    }

    private function method(array $fields): int
    {
        return $this->postJson(self::METHODS, $fields)->assertCreated()->json('data.id');
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_payment_methods_are_kept_as_their_types_allow(): void
    {
        $this->getJson(self::METHODS)->assertOk()
            ->assertJsonPath('data.methods', [])
            ->assertJsonPath('data.accounts.0.name', 'Cash Box');

        // A bank transfer says how to pay; its reference is optional unless asked otherwise.
        $this->postJson(self::METHODS, ['name' => 'Bank Transfer', 'type' => 'bank_transfer', 'payment_account_id' => $this->bank->id])
            ->assertJsonValidationErrors(['instructions' => 'Tell customers how to pay: the account or number to pay into.']);
        $this->postJson(self::METHODS, [
            'name' => 'Bank Transfer', 'type' => 'bank_transfer', 'payment_account_id' => $this->bank->id, 'instructions' => " City Bank, account 0123456789.\nUse your order number as the reference. ",
            'countries' => ['BD', 'BD'], 'max_order' => 5000,
        ])->assertCreated()
            ->assertJsonPath('message', 'Payment method added.')
            ->assertJsonPath('data.instructions', "City Bank, account 0123456789.\nUse your order number as the reference.")
            ->assertJsonPath('data.reference', 'optional')
            ->assertJsonPath('data.countries', ['BD'])
            ->assertJsonPath('data.delivery', 'any')
            ->assertJsonPath('data.payment_account', ['id' => $this->bank->id, 'name' => 'City Bank', 'currency' => 'USD', 'usable' => true]);

        // Cash on delivery: shipped orders only, no reference, whatever is sent.
        $this->postJson(self::METHODS, ['name' => 'Cash on Delivery', 'type' => 'cash_on_delivery', 'payment_account_id' => $this->cash->id, 'reference' => 'required', 'delivery' => 'pickup'])->assertCreated()
            ->assertJsonPath('data.reference', 'none')
            ->assertJsonPath('data.delivery', 'shipped');
        $this->postJson(self::METHODS, ['name' => 'Pay at the Shop', 'type' => 'pay_on_pickup', 'payment_account_id' => $this->cash->id])->assertCreated()
            ->assertJsonPath('data.delivery', 'pickup');

        $this->postJson(self::METHODS, ['name' => 'Bank Transfer', 'type' => 'pay_on_pickup', 'payment_account_id' => $this->cash->id])->assertJsonValidationErrors(['name' => 'Another payment method has this name.']);
        $this->postJson(self::METHODS, ['name' => 'bKash', 'type' => 'mobile_money', 'payment_account_id' => 999999, 'instructions' => 'Send to 01700000000.'])
            ->assertJsonValidationErrors(['payment_account_id' => 'Choose an active payment account that takes customer payments.']);
        $this->postJson(self::METHODS, ['name' => 'bKash', 'type' => 'mobile_money', 'payment_account_id' => $this->cash->id, 'instructions' => 'Send to 01700000000.', 'min_order' => 50, 'max_order' => 10])
            ->assertJsonValidationErrors(['max_order' => 'The highest order amount is below the lowest.']);

        // An account switched off is kept by a method that has it, saying it cannot be used.
        $bkash = $this->method(['name' => 'bKash', 'type' => 'mobile_money', 'payment_account_id' => $this->cash->id, 'instructions' => 'Send to 01700000000.', 'reference' => 'required']);
        $this->cash->forceFill(['is_active' => false])->save();
        $this->putJson(self::METHODS.'/'.$bkash, ['name' => 'bKash', 'type' => 'mobile_money', 'payment_account_id' => $this->cash->id, 'instructions' => 'Send money to 01700000000.', 'reference' => 'required', 'is_active' => false])->assertOk()
            ->assertJsonPath('message', 'Payment method saved.')
            ->assertJsonPath('data.payment_account.usable', false)
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(['Bank Transfer', 'Cash on Delivery', 'Pay at the Shop', 'bKash'], collect($this->getJson(self::METHODS)->json('data.methods'))->pluck('name')->all());

        $this->deleteJson(self::METHODS.'/'.$bkash)->assertOk()->assertJsonPath('message', 'Payment method deleted.');
        $this->assertTrue(ActivityLog::where('description', 'Updated the online payment method "bKash"')->exists());
        $this->assertTrue(ActivityLog::where('description', 'Deleted the online payment method "bKash"')->exists());
    }

    public function test_an_order_is_offered_the_methods_for_its_currency_country_amount_and_delivery(): void
    {
        $euros = $this->business->paymentAccounts()->create(['name' => 'Euro Bank', 'type' => 'bank', 'currency' => 'EUR']);

        $this->method(['name' => 'Bank Transfer', 'type' => 'bank_transfer', 'payment_account_id' => $this->bank->id, 'instructions' => 'Pay into City Bank.', 'countries' => ['BD'], 'max_order' => 500]);
        $this->method(['name' => 'Euro Transfer', 'type' => 'bank_transfer', 'payment_account_id' => $euros->id, 'instructions' => 'Pay into Euro Bank.']);
        $this->method(['name' => 'Cash on Delivery', 'type' => 'cash_on_delivery', 'payment_account_id' => $this->cash->id, 'min_order' => 10]);
        $this->method(['name' => 'Pay at the Shop', 'type' => 'pay_on_pickup', 'payment_account_id' => $this->cash->id]);
        $this->method(['name' => 'Off', 'type' => 'pay_on_pickup', 'payment_account_id' => $this->cash->id, 'is_active' => false]);

        $options = app(EcommercePaymentOptions::class);
        $offered = fn (string $currency, ?string $country, string $amount, string $delivery) => $options->for($this->business, $currency, $country, $amount, $delivery)->pluck('name')->all();

        $this->assertSame(['Bank Transfer', 'Cash on Delivery'], $offered('USD', 'bd', '120.00', 'shipped'));
        $this->assertSame(['Bank Transfer', 'Pay at the Shop'], $offered('USD', 'BD', '120.00', 'pickup'));
        // Nothing to deliver: neither cash on delivery nor paying on pickup.
        $this->assertSame(['Bank Transfer'], $offered('USD', 'BD', '120.00', 'none'));
        // Beyond the transfer's amount, outside its countries, below cash on delivery's least.
        $this->assertSame(['Cash on Delivery'], $offered('USD', 'BD', '600.00', 'shipped'));
        $this->assertSame(['Cash on Delivery'], $offered('USD', 'IN', '120.00', 'shipped'));
        $this->assertSame(['Bank Transfer'], $offered('USD', 'BD', '9.99', 'shipped'));
        // In euros, only the account in euros.
        $this->assertSame(['Euro Transfer'], $offered('EUR', 'BD', '120.00', 'shipped'));

        // An account that stops taking customer payments takes its methods out.
        $this->cash->forceFill(['allow_customer_payments' => false])->save();
        $this->assertSame(['Bank Transfer'], $offered('USD', 'BD', '120.00', 'shipped'));
    }

    public function test_payment_methods_are_changed_with_manage_payment_methods(): void
    {
        Sanctum::actingAs($this->member(Permissions::expand(['ecommerce.shipping.manage'])));
        $this->getJson(self::METHODS)->assertForbidden();

        Sanctum::actingAs($this->member(Permissions::expand(['ecommerce.payments.manage'])));
        $this->getJson(self::METHODS)->assertOk();
        $this->method(['name' => 'Pay at the Shop', 'type' => 'pay_on_pickup', 'payment_account_id' => $this->cash->id]);
        $this->assertEqualsCanonicalizing(['settings.view', 'ecommerce.store.view', 'ecommerce.payments.manage'], Permissions::expand(['ecommerce.payments.manage']));
    }
}
