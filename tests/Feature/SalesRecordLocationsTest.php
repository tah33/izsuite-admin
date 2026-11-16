<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Admin\LegalHold;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Support\RouteRecords;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * The full Sales app's own records (Doc 16) - deliveries, returns, advances,
 * credit applied - are each checked at their own location, not at that of
 * the sales document that happens to share their id (fixed 2026-11-16).
 * Their files open only for whoever may see them there, and a legal hold
 * stops their deletion. Wednesday 28 October 2026.
 */
class SalesRecordLocationsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private int $dhaka;

    private int $ctg;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        $full = Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 10, 'is_active' => true, 'status' => 'available']);
        $full->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->setUpSales();
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();

        $this->dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK'])->id;
        $this->ctg   = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG'])->id;
    }

    /** A confirmed order at $location with its chairs delivered: [order id, chair line id, delivery id]. */
    private function delivered(int $location): array
    {
        $order = $this->draft('orders', ['location_id' => $location]);
        $this->postJson("/api/v1/sales/orders/{$order}/confirm")->assertOk();
        $chairs   = $this->getJson("/api/v1/sales/orders/{$order}")->json('data.lines.0.id');
        $delivery = $this->postJson("/api/v1/sales/orders/{$order}/deliveries", ['lines' => [['line_id' => $chairs, 'quantity' => '2']]])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/deliveries/{$delivery}/deliver")->assertOk();

        return [$order, $chairs, $delivery];
    }

    /** A member at Dhaka only, whose role holds $permissions. */
    private function atDhaka(array $permissions): User
    {
        $user   = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $member = $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::SELECTED_LOCATIONS]);
        $member->locations()->sync([$this->dhaka]);

        return $user;
    }

    public function test_each_record_is_checked_at_its_own_location(): void
    {
        // Sales document 1 is at Chattogram; delivery 1 and return 1 are at Dhaka, delivery 2 at Chattogram.
        $this->draft('invoices', ['location_id' => $this->ctg]);
        [$order, $chairs, $delivery] = $this->delivered($this->dhaka);
        $return = $this->postJson("/api/v1/sales/orders/{$order}/returns", ['reason' => 'Wrong colour', 'lines' => [['line_id' => $chairs, 'quantity' => '1']]])->assertCreated()->json('data.id');
        [, , $theirs] = $this->delivered($this->ctg);
        $this->assertSame([1, 1, 2], [$delivery, $return, $theirs]);

        Sanctum::actingAs($this->atDhaka(['sales.orders.view', 'sales.deliveries.view', 'sales.returns.view']));

        // Their own location's records open, whatever sales document shares their id.
        $this->getJson("/api/v1/sales/deliveries/{$delivery}")->assertOk();
        $this->getJson("/api/v1/sales/returns/{$return}")->assertOk();

        // Another location's delivery does not, though sales document 2 is at Dhaka.
        $this->getJson("/api/v1/sales/deliveries/{$theirs}")->assertForbidden()->assertJsonPath('message', 'This record is at a location you do not have access to.');
        $this->getJson('/api/v1/sales/deliveries')->assertOk()->assertJsonPath('pagination.total', 1);

        // Each kind of record is found by its own id.
        $this->assertSame(
            [RouteRecords::SALES_DOCUMENT, RouteRecords::SALES_DELIVERY, RouteRecords::SALES_DOCUMENT, RouteRecords::SALES_RETURN, RouteRecords::CUSTOMER_ADVANCE, RouteRecords::SALES_ALLOCATION, RouteRecords::SALES_DOCUMENT, null, null, RouteRecords::SALES_PAYMENT, RouteRecords::SALES_DOCUMENT],
            array_map(fn (string $route) => RouteRecords::kindOf($route), [
                'api.sales.deliveries.store', 'api.sales.deliveries.deliver', 'api.sales.returns.store', 'api.sales.returns.authorise', 'api.sales.advances.reverse',
                'api.sales.allocations.remove', 'api.sales.allocations.store', 'api.sales.price-lists.update', 'api.sales.customers.credit', 'api.sales.payments.show', 'api.sales.show',
            ]),
        );
    }

    public function test_delivery_and_return_files_open_only_where_they_may_be_seen_and_a_hold_keeps_them(): void
    {
        [$order, $chairs, $delivery] = $this->delivered($this->ctg);
        $return = $this->postJson("/api/v1/sales/orders/{$order}/returns", ['reason' => 'Wrong colour', 'lines' => [['line_id' => $chairs, 'quantity' => '1']]])->assertCreated()->json('data.id');

        $upload = fn (string $path) => $this->post($path, ['files' => [UploadedFile::fake()->create('note.pdf', 20, 'application/pdf')]], ['Accept' => 'application/json'])->assertCreated()->json('data.0.id');
        $deliveryFile = $upload("/api/v1/sales/deliveries/{$delivery}/attachments");
        $returnFile   = $upload("/api/v1/sales/returns/{$return}/attachments");

        // Without the permission, or at another location, a file does not open.
        Sanctum::actingAs($this->atDhaka(['sales.orders.view']));
        $this->getJson("/api/v1/files/{$deliveryFile}/link")->assertForbidden();
        $this->getJson("/api/v1/files/{$returnFile}/link")->assertForbidden();

        Sanctum::actingAs($this->atDhaka(['sales.orders.view', 'sales.deliveries.view', 'sales.returns.view']));
        $this->getJson("/api/v1/files/{$deliveryFile}/link")->assertForbidden()->assertJsonPath('message', 'This record is at a location you do not have access to.');

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/files/{$returnFile}/link")->assertOk();

        // A legal hold stops the draft return, and both files, being deleted.
        LegalHold::create(['workspace_id' => $this->business->id, 'business_name' => $this->business->name, 'reference' => 'Case 1', 'reason' => 'A court asked for the records.', 'placed_by' => $this->user->id, 'placed_at' => now()]);

        $this->deleteJson("/api/v1/sales/returns/{$return}")->assertForbidden()->assertJsonPath('legal_hold', true);
        $this->deleteJson("/api/v1/sales/returns/{$return}/attachments/{$returnFile}")->assertForbidden()->assertJsonPath('legal_hold', true);
        $this->deleteJson("/api/v1/sales/deliveries/{$delivery}/attachments/{$deliveryFile}")->assertForbidden()->assertJsonPath('legal_hold', true);
    }
}
