<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase F3: a session's X and Z reports (sections 18.27 and 18.28), on
 * the receipt's roll - at Front Counter (R1) in Dhaka, taking Cash into the
 * Shop Till: a float of 50.00, a cash sale of a mug at 15.00, and a safe drop
 * of 20.00. The X report while it is open; the Z report once it is counted.
 */
class PosSessionReportsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const SESSIONS = '/api/v1/pos/sessions';

    private Workspace $business;

    private int $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        Application::create(['app_key' => 'pos', 'name' => 'Point of Sale', 'family_key' => 'pos', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 30, 'is_active' => true, 'status' => 'available'])
            ->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-20 10:00:00', 'UTC'));
        $user           = $this->signIn();
        $this->business = $this->businessOf($user);
        $this->business->forceFill(['base_currency' => 'USD', 'tax_mode' => 'later'])->save();
        $dhaka = $this->business->locations()->create(['user_id' => $user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $cash = (int) $this->getJson('/api/v1/pos/registers/options')->assertOk()->json('data.payment_methods.0.id');
        $safe = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Safe', 'currency' => 'USD']);

        $walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $walkIn->id]);
        $mug = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '15.00']);

        $register      = $this->postJson('/api/v1/pos/registers', [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$cash], 'cashier_ids' => [$user->id],
        ])->assertCreated()->json('data');
        $this->session = $this->postJson(self::SESSIONS, ['pos_register_id' => $register['id'], 'opening_float' => '50'])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/pos/orders', [
            'contact_id' => $walkIn->id, 'lines' => [['item_id' => $mug->id, 'quantity' => '1']], 'total' => '15.00', 'payments' => [['method_id' => $cash, 'amount' => '15.00']],
        ])->assertCreated();
        $this->postJson('/api/v1/pos/cash-movements', ['type' => 'safe_drop', 'account_id' => $safe->id, 'amount' => '20', 'reason' => 'Too much cash'])->assertCreated();

        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
    }

    /** The HTML the last PDF was made from. */
    private function html(): string
    {
        /** @var Request $request */
        [$request] = Http::recorded()->last();

        return (string) collect($request->data())->firstWhere('filename', 'index.html')['contents'];
    }

    public function test_an_x_report_shows_the_open_session_so_far(): void
    {
        $this->get(self::SESSIONS."/{$this->session}/x-report", ['Accept' => 'application/json'])->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename=X-Report-S-00001.pdf');

        $html = $this->html();
        foreach (['X Report', 'S-00001', 'Front Counter (R1)', 'Sales (1)', '$15.00', 'Opening cash', '$50.00', 'Cash out', '−$20.00', 'Cash expected', '$45.00', 'Not a closing: the session stays open.'] as $shown) {
            $this->assertStringContainsString($shown, $html);
        }
        $this->assertStringNotContainsString('Cash counted', $html);
        $this->assertSame(1, ActivityLog::where('description', 'Printed the X report of the POS session S-00001')->count());

        // No Z report while it is open; no other kind of report.
        $this->getJson(self::SESSIONS."/{$this->session}/z-report")->assertConflict()->assertJsonPath('message', 'Session S-00001 is open: its X report shows it so far.');
        $this->getJson(self::SESSIONS."/{$this->session}/y-report")->assertNotFound();
    }

    public function test_a_z_report_shows_how_the_session_closed(): void
    {
        $this->postJson(self::SESSIONS."/{$this->session}/close", ['counted_cash' => '43', 'variance_reason' => 'Two dollars short'])->assertOk();

        $this->get(self::SESSIONS."/{$this->session}/z-report", ['Accept' => 'application/json'])->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename=Z-Report-S-00001.pdf');

        $html = $this->html();
        foreach (['Z Report', 'S-00001', 'Closed', 'Cash expected', '$45.00', 'Cash counted', '$43.00', 'Variance', 'Two dollars short'] as $shown) {
            $this->assertStringContainsString($shown, $html);
        }
        $this->assertStringNotContainsString('Not a closing', $html);

        $this->getJson(self::SESSIONS."/{$this->session}/x-report")->assertConflict()->assertJsonPath('message', 'Session S-00001 is closed: its Z report shows how it closed.');
    }

    public function test_the_reports_are_for_whoever_may_view_sessions(): void
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Contacts only', 'permissions' => Permissions::expand(['contacts.view'])]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);
        Sanctum::actingAs($user);

        $this->getJson(self::SESSIONS."/{$this->session}/x-report")->assertForbidden();
        $this->getJson(self::SESSIONS.'/99999/x-report')->assertForbidden();
    }
}
