<?php

namespace Tests\Feature;

use App\Models\User\Location;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\Shared\LocationAccess;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use App\Support\RouteRecords;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Where a member may work (Doc 12 sections 18 to 21) - Doc 12 Phase C2: a
 * member with Selected Locations sees, opens and makes records only at
 * those - lists, the Documents page, the Dashboard and Reports with them -
 * and uses the whole business's payment accounts and their locations'.
 * Settings > Locations counts each location's users and assigns them. The
 * owner sees every location.
 */
class LocationAccessTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Location $dhaka;

    private Location $chattogram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));

        $this->offerSalesLite();
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);

        app(SetUpNewWorkspace::class)->addRoles($this->business, $this->user, 'api');
        $this->setUpSales();

        $this->dhaka      = $this->place('Dhaka', 'DHK', primary: true);
        $this->chattogram = $this->place('Chattogram', 'CTG');
    }

    private function place(string $name, string $code, bool $primary = false): Location
    {
        return $this->business->locations()->create(['user_id' => $this->user->id, 'name' => $name, 'code' => $code, 'is_primary' => $primary, 'country' => 'BD', 'timezone' => 'Asia/Dhaka']);
    }

    /**
     * A user of the business under template $role, with $locations chosen -
     * null for All Locations.
     *
     * @param  list<Location>|null  $locations
     * @return array{0: User, 1: WorkspaceMember}
     */
    private function memberRow(string $role, ?array $locations, ?Location $default = null): array
    {
        $user   = User::factory()->create();
        $member = $this->business->members()->create([
            'user_id'             => $user->id,
            'role_id'             => $this->business->roles()->where('name', $role)->value('id'),
            'name'                => $user->name,
            'email'               => $user->email,
            'location_access'     => $locations === null ? WorkspaceMember::ALL_LOCATIONS : WorkspaceMember::SELECTED_LOCATIONS,
            'default_location_id' => $default?->id,
        ]);

        $member->locations()->sync(array_map(fn (Location $location) => $location->id, $locations ?? []));

        return [$user, $member];
    }

    /** As memberRow(), signed in - the header that picks the business. */
    private function member(string $role, ?array $locations, ?Location $default = null): array
    {
        Sanctum::actingAs($this->memberRow($role, $locations, $default)[0]);

        return $this->pick($this->business);
    }

    public function test_a_member_sees_only_what_is_at_their_locations(): void
    {
        $ours   = $this->issuedInvoice(['location_id' => $this->dhaka->id]);
        $theirs = $this->issuedInvoice(['location_id' => $this->chattogram->id]);

        $this->assertCount(2, $this->getJson('/api/v1/sales/invoices')->json('data'));
        $this->assertSame('671.00', $this->getJson('/api/v1/dashboard')->json('data.sales.outstanding'));

        $pick = $this->member('Manager', [$this->dhaka]);
        $ids  = fn (string $url) => array_column($this->getJson($url, $pick)->assertOk()->json('data'), 'id');

        $this->assertSame([$ours], $ids('/api/v1/sales/invoices'));
        $this->assertSame([$ours], $ids('/api/v1/documents'));
        $this->assertSame([$this->dhaka->id], $ids('/api/v1/locations'));

        // The other location's invoice - its PDF too - is not theirs to open.
        $this->getJson("/api/v1/sales/invoices/{$ours}", $pick)->assertOk();
        $this->getJson("/api/v1/sales/invoices/{$theirs}", $pick)->assertForbidden()->assertJsonPath('message', LocationAccess::REFUSED);
        $this->getJson("/api/v1/sales/invoices/{$theirs}/pdf", $pick)->assertForbidden();

        // The Dashboard and Reports count their location alone.
        $this->assertSame('335.50', $this->getJson('/api/v1/dashboard', $pick)->json('data.sales.outstanding'));
        $this->assertSame(["/sales/invoices/{$ours}"], array_column($this->getJson('/api/v1/reports/outstanding-invoices', $pick)->assertOk()->json('data.rows'), 'link'));
    }

    public function test_a_member_makes_records_only_at_their_locations(): void
    {
        $sylhet = $this->place('Sylhet', 'SYL');
        $pick   = $this->member('Sales User', [$this->dhaka, $this->chattogram], $this->chattogram);

        // Something new starts at their default location.
        $this->getJson('/api/v1/sales/invoices/defaults', $pick)->assertOk()->assertJsonPath('data.location_id', $this->chattogram->id);

        $this->postJson('/api/v1/sales/invoices', $this->invoice(['location_id' => $sylhet->id]), $pick)->assertUnprocessable()->assertJsonValidationErrors('location_id');
        $this->postJson('/api/v1/sales/invoices', $this->invoice(['location_id' => null]), $pick)->assertUnprocessable()->assertJsonValidationErrors('location_id');
        $this->postJson('/api/v1/sales/invoices', $this->invoice(['location_id' => $this->dhaka->id]), $pick)->assertCreated();

        // Nor do they add a location, outside what they may use.
        $this->postJson('/api/v1/locations', ['name' => 'Khulna', 'code' => 'KHL', 'country' => 'BD', 'timezone' => 'Asia/Dhaka'], $pick)->assertForbidden();
    }

    public function test_payment_accounts_are_the_whole_business_or_the_members_locations(): void
    {
        $invoice = $this->issuedInvoice(['location_id' => $this->dhaka->id]);
        $this->account(['name' => 'Bank']);
        $here  = $this->account(['name' => 'Dhaka Cash', 'location_id' => $this->dhaka->id]);
        $there = $this->account(['name' => 'Chattogram Cash', 'location_id' => $this->chattogram->id]);

        $pick = $this->member('Manager', [$this->dhaka]);

        $this->assertSame(['Bank', 'Dhaka Cash'], array_column($this->getJson('/api/v1/payment-accounts', $pick)->assertOk()->json('data'), 'name'));
        $this->getJson("/api/v1/payment-accounts/{$there->id}", $pick)->assertForbidden();

        $this->pay($invoice, $there, '100')->assertUnprocessable()->assertJsonValidationErrors('payment_account_id');
        $this->pay($invoice, $here, '100')->assertCreated();
    }

    public function test_the_sidebar_follows_a_users_role_and_locations(): void
    {
        $this->assertTrue($this->getJson('/api/v1/navigation')->json('data.all_locations'));

        [$user, $member] = $this->memberRow('Sales User', [$this->dhaka], $this->dhaka);
        Sanctum::actingAs($user);

        $before = $this->getJson('/api/v1/navigation')->assertOk()->json('data');
        $this->assertFalse($before['all_locations']);
        $this->assertNotContains('reports.view', $before['permissions']);

        // Changed by the owner, they see it at once - not a day later.
        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/members/{$member->id}", [
            'name'            => $member->name,
            'role_id'         => $this->business->roles()->where('name', 'Manager')->value('id'),
            'location_access' => WorkspaceMember::ALL_LOCATIONS,
        ])->assertOk();

        Sanctum::actingAs($user);
        $after = $this->getJson('/api/v1/navigation')->assertOk()->json('data');
        $this->assertTrue($after['all_locations']);
        $this->assertContains('reports.view', $after['permissions']);
    }

    public function test_routes_name_the_record_they_take(): void
    {
        $this->assertSame(RouteRecords::SALES_DOCUMENT, RouteRecords::kindOf('api.sales.credit-notes.refunds.store'));
        $this->assertSame(RouteRecords::SALES_PAYMENT, RouteRecords::kindOf('api.sales.payments.pdf'));
        $this->assertSame(RouteRecords::PURCHASE, RouteRecords::kindOf('api.purchases.payments.store'));
        $this->assertSame(RouteRecords::SUPPLIER_PAYMENT, RouteRecords::kindOf('api.purchases.payments.reverse'));
        $this->assertSame(RouteRecords::EXPENSE, RouteRecords::kindOf('api.expenses.payments.store'));
        $this->assertSame(RouteRecords::ACCOUNT_TRANSFER, RouteRecords::kindOf('api.account-transfers.void'));
        $this->assertNull(RouteRecords::kindOf('api.expense-categories.update'));
        $this->assertNull(RouteRecords::kindOf('api.contacts.show'));
    }

    public function test_users_are_assigned_from_their_location(): void
    {
        [, $member] = $this->memberRow('Sales User', [$this->dhaka], $this->dhaka);

        // The owner may use both; the new user Dhaka alone.
        $counts = fn () => array_column($this->getJson('/api/v1/locations')->assertOk()->json('data'), 'users_count', 'name');
        $this->assertSame(['Chattogram' => 1, 'Dhaka' => 2], $counts());

        $this->putJson("/api/v1/locations/{$this->chattogram->id}/users", ['member_ids' => [$member->id]])
            ->assertOk()
            ->assertJsonPath('data.users_count', 2);
        $this->assertEqualsCanonicalizing([$this->dhaka->id, $this->chattogram->id], $member->locations()->pluck('locations.id')->all());
        $this->assertDatabaseHas('activity_logs', ['description' => "Gave \"{$member->name}\" access to location \"Chattogram\""]);

        // Taken from Dhaka, their default moves to the location they have left.
        $this->putJson("/api/v1/locations/{$this->dhaka->id}/users", ['member_ids' => []])->assertOk();
        $this->assertSame($this->chattogram->id, $member->fresh()->default_location_id);

        // Never their last one.
        $this->putJson("/api/v1/locations/{$this->chattogram->id}/users", ['member_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member_ids');

        // An administrator of Dhaka alone neither reaches Chattogram nor changes their own.
        $pick = $this->member('Administrator', [$this->dhaka]);
        $this->putJson("/api/v1/locations/{$this->chattogram->id}/users", ['member_ids' => []], $pick)->assertNotFound();
        $this->putJson("/api/v1/locations/{$this->dhaka->id}/users", ['member_ids' => []], $pick)->assertForbidden();
    }
}
