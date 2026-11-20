<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\PosDevice;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase I3: the devices registered at a register (section 18.30) -
 * Front Counter (R1)'s receipt printer and barcode scanner: added, changed,
 * tested, used and removed. Gotenberg is faked.
 */
class PosDevicesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private int $register;

    private string $devices;

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
        $cash = (int) $this->getJson('/api/v1/pos/registers/options')->assertOk()->json('data.payment_methods.0.id');

        $this->register = $this->postJson('/api/v1/pos/registers', [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$cash], 'cashier_ids' => [$this->user->id],
        ])->assertCreated()->json('data.id');
        $this->devices = "/api/v1/pos/registers/{$this->register}/devices";

        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
    }

    private function add(string $type, string $name, string $connection = 'browser'): int
    {
        return (int) $this->postJson($this->devices, ['type' => $type, 'name' => $name, 'connection' => $connection])->assertCreated()->assertJsonPath('message', "{$name} added.")->json('data.id');
    }

    public function test_a_register_keeps_its_devices(): void
    {
        $this->getJson($this->devices)->assertOk()->assertJsonPath('data', []);

        $printer = $this->add(PosDevice::RECEIPT_PRINTER, 'Counter Printer');
        $scanner = $this->add(PosDevice::BARCODE_SCANNER, 'Hand Scanner', 'usb');

        $this->postJson($this->devices, ['type' => 'fax', 'name' => 'Fax', 'connection' => 'browser'])->assertUnprocessable()->assertJsonValidationErrors(['type']);
        $this->postJson($this->devices, ['type' => PosDevice::CASH_DRAWER, 'name' => '', 'connection' => 'usb'])->assertUnprocessable()->assertJsonValidationErrors(['name' => 'Name the device, as the register knows it.']);

        $this->putJson("{$this->devices}/{$printer}", ['type' => PosDevice::RECEIPT_PRINTER, 'name' => 'Front Printer', 'connection' => 'network', 'address' => '192.168.1.40'])
            ->assertOk()->assertJsonPath('message', 'Front Printer saved.')->assertJsonPath('data.address', '192.168.1.40');

        $this->assertSame(['Hand Scanner', 'Front Printer'], array_column($this->getJson($this->devices)->json('data'), 'name'));

        // A scanner's test is a code it reads; a printer's a test page.
        $this->postJson("{$this->devices}/{$scanner}/test")->assertUnprocessable()->assertJsonValidationErrors(['scanned' => 'Scan a barcode with it to test it.']);
        $this->postJson("{$this->devices}/{$scanner}/test", ['scanned' => '4006381333931'])->assertOk()->assertJsonPath('message', 'Hand Scanner read 4006381333931.');
        $this->assertNotNull(PosDevice::find($scanner)->last_tested_at);

        $this->get("{$this->devices}/{$printer}/test-page")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get("{$this->devices}/{$scanner}/test-page", ['Accept' => 'application/json'])->assertStatus(409);
        $this->postJson("{$this->devices}/{$printer}/test")->assertOk()->assertJsonPath('message', 'Front Printer tested.');

        // Switched off, it is kept, but not tested.
        $this->putJson("{$this->devices}/{$scanner}", ['type' => PosDevice::BARCODE_SCANNER, 'name' => 'Hand Scanner', 'connection' => 'usb', 'is_active' => false])->assertOk();
        $this->postJson("{$this->devices}/{$scanner}/test", ['scanned' => '1'])->assertStatus(409)->assertJsonPath('message', 'Hand Scanner is switched off: switch it on to test it.');

        $this->deleteJson("{$this->devices}/{$scanner}")->assertOk()->assertJsonPath('message', 'Hand Scanner removed.');
        $this->assertSame(['Front Printer'], array_column($this->getJson($this->devices)->json('data'), 'name'));
    }

    public function test_a_receipt_printed_and_an_item_scanned_use_them(): void
    {
        $printer = $this->add(PosDevice::RECEIPT_PRINTER, 'Counter Printer');
        $scanner = $this->add(PosDevice::BARCODE_SCANNER, 'Hand Scanner', 'usb');
        $walkIn  = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $tea     = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Black Tea', 'unit' => 'Piece', 'sale_price' => '3.50', 'barcode' => '4006381333931']);
        $cash    = (int) $this->getJson('/api/v1/pos/registers/options')->json('data.payment_methods.0.id');

        $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $this->register, 'opening_float' => '50'])->assertCreated();
        $this->getJson('/api/v1/pos/sell/items?search=4006381333931')->assertOk()->assertJsonPath('data.exact.name', 'Black Tea');
        $order = $this->postJson('/api/v1/pos/orders', ['contact_id' => $walkIn->id, 'lines' => [['item_id' => $tea->id, 'quantity' => '1']], 'total' => '3.50', 'payments' => [['method_id' => $cash, 'amount' => '3.50']]])
            ->assertCreated()->json('data.id');
        $this->get("/api/v1/pos/orders/{$order}/receipt", ['Accept' => 'application/json'])->assertOk();

        $this->assertNotNull(PosDevice::find($scanner)->last_used_at);
        $this->assertNotNull(PosDevice::find($printer)->last_used_at);
    }

    public function test_viewing_them_needs_registers_view_and_changing_them_manage_registers(): void
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Viewers', 'permissions' => Permissions::expand(['pos.registers.view'])]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);
        $printer = $this->add(PosDevice::RECEIPT_PRINTER, 'Counter Printer');
        Sanctum::actingAs($user);

        $this->getJson($this->devices)->assertOk()->assertJsonCount(1, 'data');
        $this->postJson($this->devices, ['type' => PosDevice::CASH_DRAWER, 'name' => 'Drawer', 'connection' => 'usb'])->assertForbidden();
        $this->postJson("{$this->devices}/{$printer}/test")->assertForbidden();
        $this->getJson('/api/v1/pos/registers/999/devices')->assertNotFound();
    }
}
