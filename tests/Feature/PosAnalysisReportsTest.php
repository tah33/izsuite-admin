<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\Contact;
use App\Models\User\PosOrderPayment;
use App\Models\User\PosReturn;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase H2: discounts, taxes, returns, what was called off, and items
 * (section 18.36) - at Front Counter (R1), with VAT at 15%. Two mugs at
 * 15.00 less 10% for cash, a tea overridden to 3.00 by a card payment then
 * declined, a tea at 3.50 for cash, a mug of the first sale brought back in
 * cash, and a held order cancelled.
 */
class PosAnalysisReportsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const RANGE = ['range' => 'custom', 'date_from' => '2026-11-20', 'date_to' => '2026-11-20'];

    private User $user;

    private Workspace $business;

    private Contact $walkIn;

    private int $drinks;

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
        $this->business->forceFill(['base_currency' => 'USD', 'tax_mode' => 'enabled'])->save();
        $this->business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT15', 'rate' => '15', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01'])
            ->forceFill(['default_for_sales' => true])->save();
        $dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);
        $bank = $this->business->paymentAccounts()->create(['type' => 'bank', 'name' => 'City Bank', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $cash = (int) $this->getJson('/api/v1/pos/registers/options')->assertOk()->json('data.payment_methods.0.id');
        $card = (int) $this->postJson('/api/v1/business-settings/pos/payment-methods', ['name' => 'Card', 'type' => 'card', 'payment_account_id' => $bank->id, 'confirms_later' => true])->assertCreated()->json('data.id');

        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $this->walkIn->id]);
        $this->drinks = $this->business->itemCategories()->create(['name' => 'Drinks'])->id;
        $mug = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '15.00', 'purchase_price' => '9.00']);
        $tea = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Black Tea', 'unit' => 'Piece', 'sale_price' => '3.50', 'category_id' => $this->drinks]);

        $register = $this->postJson('/api/v1/pos/registers', [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$cash, $card], 'cashier_ids' => [$this->user->id],
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $register, 'opening_float' => '50'])->assertCreated();

        $this->sell([['item_id' => $mug->id, 'quantity' => '2', 'discount_type' => 'percent', 'discount_value' => '10']], $cash);
        $this->sell([['item_id' => $tea->id, 'quantity' => '1', 'price' => '3.00', 'override_reason' => 'Dented tin']], $card);
        $this->sell([['item_id' => $tea->id, 'quantity' => '1']], $cash);
        $line = SalesDocument::where('number', 'R1-00001')->firstOrFail()->lines->first()->id;
        $this->postJson('/api/v1/pos/returns', ['order_id' => 1, 'lines' => [['line_id' => $line, 'quantity' => '1', 'condition' => 'resellable']], 'reason' => 'Chipped', 'refund' => 'cash'])->assertCreated();

        $this->travelTo(Carbon::parse('2026-11-20 05:00:00', 'UTC'));
        $held = $this->postJson('/api/v1/pos/held-orders', ['contact_id' => $this->walkIn->id, 'lines' => [['item_id' => $tea->id, 'quantity' => '3']]])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/pos/held-orders/{$held}/cancel", ['reason' => 'Customer left'])->assertOk();

        $this->travelTo(Carbon::parse('2026-11-20 06:00:00', 'UTC'));
        $this->postJson('/api/v1/pos/order-payments/'.PosOrderPayment::where('status', 'pending')->sole()->id.'/decline', ['reason' => 'The bank refused it'])->assertOk();
    }

    /** $lines to the walk-in customer, paid in full by $method. */
    private function sell(array $lines, int $method): void
    {
        $query = ['contact_id' => $this->walkIn->id];
        foreach ($lines as $at => $line) {
            foreach ($line as $field => $value) {
                $query["lines[{$at}][{$field}]"] = $value;
            }
        }
        $total = $this->getJson('/api/v1/pos/sell/quote?'.http_build_query($query))->assertOk()->json('data.grand_total');

        $this->postJson('/api/v1/pos/orders', ['contact_id' => $this->walkIn->id, 'lines' => $lines, 'total' => $total, 'payments' => [['method_id' => $method, 'amount' => $total]]])->assertCreated();
    }

    private function report(string $key, array $query = []): TestResponse
    {
        return $this->getJson("/api/v1/reports/{$key}?".http_build_query([...self::RANGE, ...$query]))->assertOk();
    }

    /** Each row's $keys, in their order. */
    private function rows(TestResponse $response, array $keys): array
    {
        return array_map(fn (array $row) => array_combine($keys, array_map(fn (string $key) => $row[$key] ?? null, $keys)), $response->json('data.rows'));
    }

    public function test_discounts_and_price_overrides(): void
    {
        $report = $this->report('pos-discounts');

        $this->assertSame([
            ['number' => 'R1-00001', 'before' => '30.00', 'discount' => '3.00', 'percent' => '10.0', 'overrides' => 0, 'change' => '0.00', 'reason' => null],
            ['number' => 'R1-00002', 'before' => '3.00', 'discount' => '0.00', 'percent' => '0.0', 'overrides' => 1, 'change' => '-0.50', 'reason' => 'Dented tin'],
        ], $this->rows($report, ['number', 'before', 'discount', 'percent', 'overrides', 'change', 'reason']));
        $this->assertSame(['3.00', '0.00', '-0.50'], array_column($report->json('data.summary'), 'value'));
    }

    public function test_taxes_collected_and_returns(): void
    {
        $note = PosReturn::sole()->creditNote;

        $this->assertSame([[
            'tax' => 'VAT 15%', 'sales_taxable' => '33.50', 'sales_tax' => '5.03', 'returns_taxable' => '13.50', 'returns_tax' => (string) $note->tax_total,
            'net_taxable' => '20.00', 'net_tax' => bcsub('5.03', (string) $note->tax_total, 2),
        ]], $this->rows($this->report('pos-taxes'), ['tax', 'sales_taxable', 'sales_tax', 'returns_taxable', 'returns_tax', 'net_taxable', 'net_tax']));

        $this->assertSame([[
            'number' => 'RT-00001', 'sale' => 'R1-00001', 'reason' => 'Chipped', 'credited' => (string) $note->grand_total, 'method' => 'Cash', 'refunded' => (string) $note->grand_total, 'status' => 'Paid',
        ]], $this->rows($this->report('pos-returns'), ['number', 'sale', 'reason', 'credited', 'method', 'refunded', 'status']));
    }

    public function test_what_was_called_off(): void
    {
        $this->assertSame([
            ['date' => '2026-11-20', 'kind' => 'Held order cancelled', 'number' => 'H-00001', 'amount' => '12.08', 'currency' => 'USD', 'by' => $this->user->name, 'reason' => 'Customer left'],
            ['date' => '2026-11-20', 'kind' => 'Card payment declined', 'number' => 'R1-00002', 'amount' => '3.45', 'currency' => 'USD', 'by' => $this->user->name, 'reason' => 'The bank refused it'],
        ], $this->rows($this->report('pos-voids'), ['date', 'kind', 'number', 'amount', 'currency', 'by', 'reason']));

        // The next day's report has neither.
        $this->assertSame([], $this->report('pos-voids', ['date_from' => '2026-11-21', 'date_to' => '2026-11-21'])->json('data.rows'));
    }

    public function test_top_items_and_the_estimated_margin(): void
    {
        $this->assertSame([
            ['rank' => 1, 'group' => 'Black Tea', 'net_quantity' => '2.000', 'net' => '6.50', 'share' => '32.5'],
            ['rank' => 2, 'group' => 'Mug', 'net_quantity' => '1.000', 'net' => '13.50', 'share' => '67.5'],
        ], $this->rows($this->report('pos-top-items'), ['rank', 'group', 'net_quantity', 'net', 'share']));
        $this->assertSame(['Black Tea'], array_column($this->report('pos-top-items', ['category_id' => $this->drinks])->json('data.rows'), 'group'));

        $margin = $this->report('pos-margin');
        $this->assertSame([
            ['group' => 'Mug', 'quantity' => '1.000', 'net' => '13.50', 'cost' => '9.00', 'margin' => '4.50', 'percent' => '33.3'],
            ['group' => 'Black Tea', 'quantity' => '2.000', 'net' => '6.50', 'cost' => null, 'margin' => null, 'percent' => null],
        ], $this->rows($margin, ['group', 'quantity', 'net', 'cost', 'margin', 'percent']));
        $this->assertSame('Total of items with a cost', $margin->json('data.totals.group'));
    }

    public function test_each_needs_the_view_of_what_it_counts(): void
    {
        $member = function (array $permissions): array {
            $user = User::factory()->create();
            $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => Permissions::expand($permissions)]);
            $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);
            Sanctum::actingAs($user);

            return collect($this->getJson('/api/v1/reports')->assertOk()->json('data'))->where('category', 'Point of Sale')->pluck('key')->values()->all();
        };

        $phase = ['pos-discounts', 'pos-taxes', 'pos-returns', 'pos-voids', 'pos-top-items', 'pos-margin'];

        $this->assertSame(['pos-discounts', 'pos-voids', 'pos-top-items'], array_values(array_intersect($member(['pos.reports.view', 'pos.orders.view']), $phase)));
        $this->assertSame(['pos-discounts', 'pos-taxes', 'pos-returns', 'pos-voids', 'pos-top-items', 'pos-margin'], array_values(array_intersect($member(['pos.reports.view', 'pos.orders.view', 'pos.returns.view', 'taxes.view', 'items.costs']), $phase)));
        $this->assertSame(['pos-returns'], array_values(array_intersect($member(['pos.reports.view', 'pos.returns.view']), $phase)));
    }
}
