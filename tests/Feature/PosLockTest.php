<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase I1: a cashier's PIN and their register's lock (section 18.31)
 * - at Front Counter (R1), which locks after 5 minutes without use.
 */
class PosLockTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Contact $walkIn;

    private int $cash;

    private int $mug;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        Application::create(['app_key' => 'pos', 'name' => 'Point of Sale', 'family_key' => 'pos', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 30, 'is_active' => true, 'status' => 'available'])
            ->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-20 04:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'tax_mode' => 'later'])->save();
        $dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $this->cash   = (int) $this->getJson('/api/v1/pos/registers/options')->assertOk()->json('data.payment_methods.0.id');
        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $this->walkIn->id]);
        $this->mug = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '15.00'])->id;

        $this->putJson('/api/v1/business-settings/pos/selling', ['discount_limit' => null, 'cash_rounding' => null, 'hold_hours' => null, 'lock_minutes' => '5'])
            ->assertOk()->assertJsonPath('data.lock_minutes', 5);

        $register = $this->postJson('/api/v1/pos/registers', [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => [$this->user->id],
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $register, 'opening_float' => '50'])->assertCreated();
    }

    private function sell(): TestResponse
    {
        return $this->postJson('/api/v1/pos/orders', [
            'contact_id' => $this->walkIn->id, 'lines' => [['item_id' => $this->mug, 'quantity' => '1']], 'total' => '15.00', 'payments' => [['method_id' => $this->cash, 'amount' => '15.00']],
        ]);
    }

    public function test_the_cashier_locks_the_register_and_opens_it_with_their_password_until_they_set_a_pin(): void
    {
        $this->getJson('/api/v1/pos/sell')->assertOk()->assertJsonPath('data.lock', ['locked' => false, 'minutes' => 5, 'has_pin' => false]);

        $this->postJson('/api/v1/pos/lock')->assertOk()->assertJsonPath('message', 'Front Counter (R1) is locked.')->assertJsonPath('data.locked', true);
        $this->sell()->assertStatus(423)->assertJsonPath('message', 'Front Counter (R1) is locked: enter your password to go on.');
        $this->getJson('/api/v1/pos/sell')->assertOk()->assertJsonPath('data.lock.locked', true);

        // No PIN yet: their password opens it.
        $this->postJson('/api/v1/pos/unlock', ['password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors(['password' => 'That is not your password.']);
        $this->postJson('/api/v1/pos/unlock', ['password' => 'password'])->assertOk()->assertJsonPath('message', 'Front Counter (R1) is open again.');
        $this->sell()->assertCreated();

        // A PIN: 4 to 6 digits, typed twice, with their password.
        $this->putJson('/api/v1/pos/pin', ['pin' => '12', 'pin_confirmation' => '12', 'password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors(['pin' => 'A PIN is 4 to 6 digits.']);
        $this->putJson('/api/v1/pos/pin', ['pin' => '2580', 'pin_confirmation' => '2580', 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors(['password' => 'That is not your password.']);
        $this->putJson('/api/v1/pos/pin', ['pin' => '2580', 'pin_confirmation' => '2580', 'password' => 'password'])->assertOk()->assertJsonPath('message', 'Your PIN is saved.');

        $user = $this->user->fresh();
        $this->assertTrue(Hash::check('2580', $user->pos_pin));
        $this->assertArrayNotHasKey('pos_pin', $user->toArray());
        $this->assertSame(1, ActivityLog::where('description', 'Set their POS PIN')->count());
    }

    public function test_it_locks_itself_after_the_minutes_without_use_and_opens_with_the_pin(): void
    {
        $this->putJson('/api/v1/pos/pin', ['pin' => '2580', 'pin_confirmation' => '2580', 'password' => 'password'])->assertOk();
        $this->sell()->assertCreated();

        $this->travel(4)->minutes();
        $this->sell()->assertCreated();

        $this->travel(6)->minutes();
        $this->sell()->assertStatus(423);
        $this->getJson('/api/v1/pos/sell')->assertOk()->assertJsonPath('data.lock', ['locked' => true, 'minutes' => 5, 'has_pin' => true]);

        $this->postJson('/api/v1/pos/unlock', ['pin' => '0000'])->assertUnprocessable()->assertJsonValidationErrors(['pin' => 'That PIN is not right.']);
        $this->postJson('/api/v1/pos/unlock', ['password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors(['pin' => 'That PIN is not right.']);
        $this->postJson('/api/v1/pos/unlock', ['pin' => '2580'])->assertOk();
        $this->sell()->assertCreated();
    }

    public function test_only_ones_own_open_session_locks(): void
    {
        $session = $this->getJson('/api/v1/pos/sell')->json('data.session.id');
        $this->postJson("/api/v1/pos/sessions/{$session}/close", ['counted_cash' => '50'])->assertOk();

        $this->postJson('/api/v1/pos/lock')->assertStatus(409)->assertJsonPath('message', 'Open your session first: a register locks for its cashier.');
        $this->putJson('/api/v1/pos/pin', ['pin' => '2580', 'pin_confirmation' => '2580', 'password' => 'password'])->assertOk();

        // Settings: up to 4 hours.
        $this->putJson('/api/v1/business-settings/pos/selling', ['lock_minutes' => '300'])->assertUnprocessable()
            ->assertJsonValidationErrors(['lock_minutes' => 'Lock a register after up to 240 minutes (4 hours).']);
        $this->putJson('/api/v1/business-settings/pos/selling', ['lock_minutes' => null])->assertOk()->assertJsonPath('data.lock_minutes', null);
    }
}
