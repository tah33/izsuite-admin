<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Location;
use App\Models\User\PaymentAccount;
use App\Models\User\PriceList;
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
 * Doc 18 Phase A2: POS registers (section 18.5) - each at one location,
 * selling in one currency, with the payment methods it takes, its cashiers,
 * its cash account, price list and receipt prefix, and its status - kept
 * under Manage Registers, and seen where their location may be used.
 */
class PosRegistersTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const REGISTERS = '/api/v1/pos/registers';

    private User $user;

    private Workspace $business;

    private Location $dhaka;

    private Location $chattogram;

    private PaymentAccount $till;

    /** The payment methods by name. */
    private array $methods = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $category = Application::sole()->category_id;
        $pos      = Application::create(['app_key' => 'pos', 'name' => 'Point of Sale', 'family_key' => 'pos', 'edition' => 'full', 'category_id' => $category, 'sort_order' => 30, 'is_active' => true, 'status' => 'available']);
        $pos->packages()->attach(Package::pluck('id'));
        $sales = Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => $category, 'sort_order' => 10, 'is_active' => true, 'status' => 'available']);
        $sales->packages()->attach(Package::pluck('id'));

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'additional_currencies' => ['EUR']])->save();
        $this->dhaka      = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->chattogram = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);
        $this->till       = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);
        $bank             = $this->business->paymentAccounts()->create(['type' => 'bank', 'name' => 'City Bank', 'currency' => 'USD']);
        $euros            = $this->business->paymentAccounts()->create(['type' => 'bank', 'name' => 'Euro Bank', 'currency' => 'EUR']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        // The registers' form starts the POS settings - with their Cash method - when Settings has not.
        $this->assertSame(['Cash'], array_column($this->getJson(self::REGISTERS.'/options')->assertOk()->json('data.payment_methods'), 'name'));
        $this->postJson('/api/v1/business-settings/pos/payment-methods', ['name' => 'Card', 'type' => 'card', 'payment_account_id' => $bank->id, 'needs_reference' => true])->assertCreated();
        $this->postJson('/api/v1/business-settings/pos/payment-methods', ['name' => 'Euro Card', 'type' => 'card', 'payment_account_id' => $euros->id])->assertCreated();
        $this->methods = collect($this->getJson('/api/v1/business-settings/pos')->json('data.payment_methods'))->pluck('id', 'name')->all();
    }

    /** A member called $first $last whose role holds $permissions - at $locations only, when given. */
    private function member(string $first, string $last, array $permissions, ?array $locations = null): User
    {
        $user   = User::factory()->create(['first_name' => $first, 'last_name' => $last]);
        $role   = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $member = $this->business->members()->create([
            'user_id'         => $user->id,
            'status'          => 'active',
            'role_id'         => $role->id,
            'location_access' => $locations === null ? WorkspaceMember::ALL_LOCATIONS : WorkspaceMember::SELECTED_LOCATIONS,
        ]);
        $member->locations()->sync($locations ?? []);

        return $user;
    }

    /** A Dhaka register in dollars taking Cash and Card, unless $fields say otherwise. */
    private function register(array $fields = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(self::REGISTERS, [
            'name' => 'Front Counter', 'code' => 'r1', 'location_id' => $this->dhaka->id, 'currency' => 'USD',
            'payment_method_ids' => [$this->methods['Cash'], $this->methods['Card']], ...$fields,
        ]);
    }

    public function test_registers_are_kept_with_their_methods_cashiers_and_status(): void
    {
        $ana = $this->member('Ana', 'Ahmed', ['pos.registers.view']);
        $bob = $this->member('Bob', 'Biswas', ['pos.registers.view'], [$this->chattogram->id]);

        $options = $this->getJson(self::REGISTERS.'/options')->assertOk()->json('data');
        $this->assertSame(['USD', 'EUR'], $options['currencies']);
        $this->assertSame(['Cash', 'Card', 'Euro Card'], array_column($options['payment_methods'], 'name'));
        $this->assertSame(['USD', 'USD', 'EUR'], array_column($options['payment_methods'], 'currency'));
        $this->assertSame([null, [$this->chattogram->id]], [collect($options['cashiers'])->firstWhere('name', 'Ana Ahmed')['locations'], collect($options['cashiers'])->firstWhere('name', 'Bob Biswas')['locations']]);

        $register = $this->register(['cashier_ids' => [$this->user->id, $ana->id]])->assertCreated()
            ->assertJsonPath('message', 'Register added.')
            ->assertJsonPath('data.code', 'R1')
            ->assertJsonPath('data.receipt_prefix', 'R1-')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.location', ['id' => $this->dhaka->id, 'name' => 'Dhaka'])
            ->assertJsonPath('data.payment_methods.*.name', ['Cash', 'Card'])
            ->json('data');
        $this->assertEqualsCanonicalizing(['Ana Ahmed', $this->user->name], array_column($register['cashiers'], 'name'));
        $this->assertTrue(ActivityLog::query()->where('description', 'Added the POS register "Front Counter" (R1)')->exists());

        // Refused: a name or code taken, a method in another currency, a cashier who may not use the location, a currency not the business's, no methods.
        $this->register(['code' => 'R2'])->assertUnprocessable()->assertJsonValidationErrors(['name' => 'Another register has this name.']);
        $this->register(['name' => 'Back Counter', 'code' => 'R1'])->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Another register has this code.']);
        $this->register(['name' => 'Back Counter', 'code' => 'R2', 'payment_method_ids' => [$this->methods['Euro Card']]])->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method_ids' => "Choose payment methods switched on whose accounts take customer payments in the register's currency: \"Euro Card\" does not."]);
        $this->register(['name' => 'Back Counter', 'code' => 'R2', 'cashier_ids' => [$bob->id]])->assertUnprocessable()
            ->assertJsonValidationErrors(['cashier_ids' => "Choose active members who may use the register's location: Bob Biswas may not."]);
        $this->register(['name' => 'Back Counter', 'code' => 'R2', 'currency' => 'GBP'])->assertUnprocessable()->assertJsonValidationErrors(['currency' => "Choose one of the business's currencies."]);
        $this->register(['name' => 'Back Counter', 'code' => 'R2', 'payment_method_ids' => []])->assertUnprocessable()->assertJsonValidationErrors(['payment_method_ids' => 'Choose the payment methods it takes.']);
        $this->register(['name' => 'Back Counter', 'code' => 'R 2'])->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Use letters, digits and dashes.']);

        // A method switched off is named.
        $this->putJson("/api/v1/business-settings/pos/payment-methods/{$this->methods['Card']}", ['name' => 'Card', 'type' => 'card', 'payment_account_id' => $this->business->paymentAccounts()->where('name', 'City Bank')->value('id'), 'is_active' => false])->assertOk();
        $this->register(['name' => 'Back Counter', 'code' => 'R2'])->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method_ids' => "Choose payment methods switched on whose accounts take customer payments in the register's currency: \"Card\" does not."]);

        // A Chattogram register in euros takes the euro card, sold at by Bob.
        $this->register(['name' => 'Port Counter', 'code' => 'CTG1', 'location_id' => $this->chattogram->id, 'currency' => 'EUR', 'payment_method_ids' => [$this->methods['Euro Card']], 'cashier_ids' => [$bob->id]])->assertCreated();

        // Changed: its own cash account, another prefix, and Inactive.
        $this->putJson(self::REGISTERS."/{$register['id']}", [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'cash_account_id' => $this->till->id, 'receipt_prefix' => 'FC/',
            'payment_method_ids' => [$this->methods['Cash']], 'cashier_ids' => [$ana->id], 'status' => 'inactive',
        ])->assertOk()
            ->assertJsonPath('message', 'Register saved.')
            ->assertJsonPath('data.cash_account', ['id' => $this->till->id, 'name' => 'Shop Till'])
            ->assertJsonPath('data.receipt_prefix', 'FC/')
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.cashiers.*.name', ['Ana Ahmed']);
        $this->assertTrue(ActivityLog::query()->where('description', 'Updated the POS register "Front Counter" (R1)')->exists());

        // Each seen where its location may be used: Bob sees Chattogram's alone.
        Sanctum::actingAs($bob);
        $this->assertSame(['Port Counter'], array_column($this->getJson(self::REGISTERS)->assertOk()->json('data'), 'name'));
        $this->getJson(self::REGISTERS.'/options')->assertForbidden();
        Sanctum::actingAs($this->user);
        $this->assertSame(['Front Counter', 'Port Counter'], array_column($this->getJson(self::REGISTERS)->json('data'), 'name'));

        // A method a register takes, and a register's location, stay; a register is deleted.
        $this->deleteJson("/api/v1/business-settings/pos/payment-methods/{$this->methods['Euro Card']}")->assertStatus(409)
            ->assertJsonPath('message', 'Registers take this method (Port Counter). Take it off them, or switch it off instead.');
        $this->deleteJson("/api/v1/locations/{$this->chattogram->id}")->assertStatus(409)->assertJsonPath('message', 'POS registers belong to this location, so it cannot be deleted. Deactivate it instead.');
        $this->deleteJson(self::REGISTERS."/{$register['id']}")->assertOk()->assertJsonPath('message', 'Register deleted.');
        $this->assertTrue(ActivityLog::query()->where('description', 'Deleted the POS register "Front Counter" (R1)')->exists());
    }

    public function test_its_cash_account_and_price_list_suit_its_currency_and_location(): void
    {
        $portTill = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Port Till', 'currency' => 'USD', 'location_id' => $this->chattogram->id]);
        $euroTill = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Euro Till', 'currency' => 'EUR']);
        $closed   = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Sylhet', 'code' => 'SYL', 'is_active' => false]);

        $this->register(['location_id' => $closed->id])->assertUnprocessable()->assertJsonValidationErrors(['location_id' => 'Choose one of your locations that is switched on.']);
        $this->register(['cash_account_id' => $euroTill->id])->assertUnprocessable()->assertJsonValidationErrors('cash_account_id');
        $this->register(['cash_account_id' => $portTill->id])->assertUnprocessable()
            ->assertJsonValidationErrors(['cash_account_id' => "Choose an active account taking customer payments, in the register's currency, of its location or the whole business."]);

        // Price lists are the full Sales app's: in its currency, for its location.
        $list = PriceList::query()->forceCreate(['workspace_id' => $this->business->id, 'name' => 'Port Prices', 'currency' => 'USD', 'is_active' => true, 'all_customers' => true, 'all_locations' => false]);
        $list->locations()->attach($this->chattogram->id);
        $this->assertSame([], $this->getJson(self::REGISTERS.'/options')->json('data.price_lists'));
        $this->register(['price_list_id' => $list->id])->assertUnprocessable()->assertJsonValidationErrors('price_list_id');
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
        $this->assertSame([['id' => $list->id, 'name' => 'Port Prices', 'currency' => 'USD', 'location_ids' => [$this->chattogram->id]]], $this->getJson(self::REGISTERS.'/options')->json('data.price_lists'));
        $this->register(['price_list_id' => $list->id])->assertUnprocessable()->assertJsonValidationErrors(['price_list_id' => "Choose an active price list in the register's currency, for its location."]);
        $this->register(['location_id' => $this->chattogram->id, 'price_list_id' => $list->id, 'cash_account_id' => $portTill->id])->assertCreated()
            ->assertJsonPath('data.price_list', ['id' => $list->id, 'name' => 'Port Prices'])
            ->assertJsonPath('data.cash_account', ['id' => $portTill->id, 'name' => 'Port Till']);
    }

    public function test_manage_registers_keeps_them(): void
    {
        $this->register()->assertCreated();

        Sanctum::actingAs($this->member('Cara', 'Chowdhury', ['contacts.view']));
        $this->getJson(self::REGISTERS)->assertForbidden();
        Sanctum::actingAs($this->member('Dina', 'Das', ['pos.registers.view']));
        $this->getJson(self::REGISTERS)->assertOk()->assertJsonCount(1, 'data');
        $this->register(['name' => 'Back Counter', 'code' => 'R2'])->assertForbidden();
        Sanctum::actingAs($this->member('Emon', 'Ali', ['pos.registers.manage']));
        $this->register(['name' => 'Back Counter', 'code' => 'R2'])->assertCreated();
    }
}
