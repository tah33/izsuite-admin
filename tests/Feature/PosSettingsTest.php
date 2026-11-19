<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\PaymentAccount;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase A1: Point of Sale comes on, with Settings → Point of Sale -
 * the walk-in customer, the receipt's return policy and footer, and the
 * payment methods (section 18.15): each mapped to an active payment account
 * of the business that takes customer payments, a Cash one to start - under
 * Manage POS Settings, while POS is on.
 */
class PosSettingsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const SETTINGS = '/api/v1/business-settings/pos';

    private User $user;

    private Workspace $business;

    private PaymentAccount $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $pos = Application::create(['app_key' => 'pos', 'name' => 'Point of Sale', 'family_key' => 'pos', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 30, 'is_active' => true, 'status' => 'available']);
        $pos->packages()->attach(Package::pluck('id'));

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'additional_currencies' => ['EUR']])->save();
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Petty Cash', 'currency' => 'EUR']);
        $this->cash = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);
    }

    /** A member whose role holds $permissions. */
    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_settings_come_with_pos_and_start_with_a_cash_method(): void
    {
        // Not there until POS is on.
        $this->getJson(self::SETTINGS)->assertForbidden();
        $this->postJson('/api/v1/apps/pos/activate')->assertOk();

        // Opened, they start with Cash on the till in the base currency, which gives change.
        $settings = $this->getJson(self::SETTINGS)->assertOk()
            ->assertJsonPath('data.walk_in_customer', null)
            ->assertJsonPath('data.payment_methods.0.name', 'Cash')
            ->assertJsonPath('data.payment_methods.0.type', 'cash')
            ->assertJsonPath('data.payment_methods.0.payment_account', ['id' => $this->cash->id, 'name' => 'Shop Till', 'currency' => 'USD', 'usable' => true])
            ->assertJsonPath('data.payment_methods.0.gives_change', true)
            ->assertJsonCount(1, 'data.payment_methods')
            ->json('data');
        $this->assertSame(['Petty Cash', 'Shop Till'], array_column($settings['accounts'], 'name'));

        // Opened again, nothing is added twice.
        $this->getJson(self::SETTINGS)->assertOk()->assertJsonCount(1, 'data.payment_methods');
    }

    public function test_the_walk_in_customer_and_the_receipt_s_words_are_saved(): void
    {
        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $walkIn   = Contact::create(['workspace_id' => $this->business->id, 'contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $supplier = Contact::create(['workspace_id' => $this->business->id, 'contact_type' => 'business', 'role' => 'supplier', 'name' => 'Rahman Supplies']);

        $this->putJson(self::SETTINGS, ['walk_in_contact_id' => $walkIn->id, 'return_policy' => 'Returns within 7 days with this receipt.', 'receipt_footer' => 'Thank you for shopping!'])->assertOk()
            ->assertJsonPath('message', 'POS settings saved.')
            ->assertJsonPath('data.walk_in_customer', ['id' => $walkIn->id, 'name' => 'Walk-in Customer'])
            ->assertJsonPath('data.return_policy', 'Returns within 7 days with this receipt.')
            ->assertJsonPath('data.receipt_footer', 'Thank you for shopping!');
        $this->assertTrue(ActivityLog::query()->where('description', 'Updated the POS settings')->exists());

        // Only an active customer; none clears it.
        $this->putJson(self::SETTINGS, ['walk_in_contact_id' => $supplier->id])->assertUnprocessable()->assertJsonValidationErrors(['walk_in_contact_id' => 'Choose one of your active customers.']);
        $walkIn->update(['is_active' => false]);
        $this->putJson(self::SETTINGS, ['walk_in_contact_id' => null, 'return_policy' => null])->assertOk()
            ->assertJsonPath('data.walk_in_customer', null)
            ->assertJsonPath('data.receipt_footer', 'Thank you for shopping!');
        $this->putJson(self::SETTINGS, ['walk_in_contact_id' => $walkIn->id])->assertUnprocessable()->assertJsonValidationErrors('walk_in_contact_id');
    }

    public function test_payment_methods_take_money_into_an_account_that_takes_customer_payments(): void
    {
        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $wallet = $this->business->paymentAccounts()->create(['type' => 'mobile_money', 'name' => 'bKash', 'currency' => 'USD']);
        $locked = $this->business->paymentAccounts()->create(['type' => 'bank', 'name' => 'Payroll Bank', 'currency' => 'USD', 'allow_customer_payments' => false]);

        $card = $this->postJson(self::SETTINGS.'/payment-methods', ['name' => 'bKash', 'type' => 'mobile_money', 'payment_account_id' => $wallet->id, 'needs_reference' => true])->assertCreated()
            ->assertJsonPath('message', 'Payment method added.')
            ->assertJsonPath('data.payment_account.name', 'bKash')
            ->assertJsonPath('data.needs_reference', true)
            ->assertJsonPath('data.gives_change', false)
            ->json('data.id');
        $this->assertSame(['Cash', 'bKash'], array_column($this->getJson(self::SETTINGS)->json('data.payment_methods'), 'name'));
        $this->assertTrue(ActivityLog::query()->where('description', 'Added the POS payment method "bKash"')->exists());

        // Refused: an account that takes no customer payments, change given but in cash, a name taken, an unknown type.
        $this->postJson(self::SETTINGS.'/payment-methods', ['name' => 'Bank', 'type' => 'bank_transfer', 'payment_account_id' => $locked->id])->assertUnprocessable()->assertJsonValidationErrors(['payment_account_id' => 'Choose an active payment account that takes customer payments.']);
        $this->postJson(self::SETTINGS.'/payment-methods', ['name' => 'Card', 'type' => 'card', 'payment_account_id' => $wallet->id, 'gives_change' => true])->assertUnprocessable()->assertJsonValidationErrors(['gives_change' => 'Change is given only in cash.']);
        $this->postJson(self::SETTINGS.'/payment-methods', ['name' => 'Cash', 'type' => 'cash', 'payment_account_id' => $this->cash->id])->assertUnprocessable()->assertJsonValidationErrors(['name' => 'Another payment method has this name.']);
        $this->postJson(self::SETTINGS.'/payment-methods', ['name' => 'Cheque', 'type' => 'cheque', 'payment_account_id' => $this->cash->id])->assertUnprocessable()->assertJsonValidationErrors('type');

        // Its account switched off, a method shows it cannot be used, and keeps it while it is changed.
        $wallet->update(['is_active' => false]);
        $this->getJson(self::SETTINGS)->assertJsonPath('data.payment_methods.1.payment_account.usable', false);
        $this->putJson(self::SETTINGS."/payment-methods/{$card}", ['name' => 'bKash Wallet', 'type' => 'mobile_money', 'payment_account_id' => $wallet->id, 'needs_reference' => true, 'is_active' => false])->assertOk()
            ->assertJsonPath('message', 'Payment method saved.')
            ->assertJsonPath('data.name', 'bKash Wallet')
            ->assertJsonPath('data.is_active', false);

        // Deleted.
        $this->deleteJson(self::SETTINGS."/payment-methods/{$card}")->assertOk()->assertJsonPath('message', 'Payment method deleted.');
        $this->assertTrue(ActivityLog::query()->where('description', 'Deleted the POS payment method "bKash Wallet"')->exists());
        $this->deleteJson(self::SETTINGS."/payment-methods/{$card}")->assertNotFound();
    }

    public function test_manage_pos_settings_keeps_them(): void
    {
        $this->postJson('/api/v1/apps/pos/activate')->assertOk();

        Sanctum::actingAs($this->member(['settings.view', 'contacts.view']));
        $this->getJson(self::SETTINGS)->assertForbidden();
        Sanctum::actingAs($this->member(['settings.view', 'contacts.view', 'pos.settings.manage']));
        $this->getJson(self::SETTINGS)->assertOk()->assertJsonPath('data.payment_methods.0.name', 'Cash');

        // Turned off, POS's settings are read but not changed; its methods stay.
        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/apps/pos/deactivate')->assertOk();
        $this->getJson(self::SETTINGS)->assertOk()->assertJsonCount(1, 'data.payment_methods');
        $this->putJson(self::SETTINGS, ['receipt_footer' => 'Closed'])->assertForbidden();
    }
}
