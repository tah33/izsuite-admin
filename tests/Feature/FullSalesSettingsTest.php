<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\PriceList;
use App\Models\User\SalesDelivery;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase H1: the rest of Settings > Sales on the full Sales app - what
 * a new sale starts as, a default price list, a deposit's starting
 * percentage, the backorder rule, starting shipping terms and the
 * salesperson - and the Dashboard's Sales Pipeline. Wednesday 28 October 2026.
 */
class FullSalesSettingsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        $full = Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 10, 'is_active' => true, 'status' => 'available']);
        $full->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->setUpSales();
    }

    private function full(): void
    {
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
    }

    private function settings(array $fields = []): TestResponse
    {
        return $this->putJson('/api/v1/business-settings/sales', [
            'payment_terms' => 0, 'quotation_validity' => 30, 'proforma_validity' => 30, 'line_discounts' => true, 'document_discounts' => true,
            ...$fields,
        ]);
    }

    /** A price list of the chair at $price - for all customers, or only another one. */
    private function list(string $name, string $price, bool $allCustomers = true): int
    {
        $other = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Someone Else']);

        return $this->postJson('/api/v1/sales/price-lists', [
            'name' => $name, 'currency' => 'USD', 'is_active' => true, 'all_customers' => $allCustomers, 'all_locations' => true, 'priority' => 5,
            ...($allCustomers ? [] : ['contact_ids' => [$other->id]]),
            'rules' => [['target' => 'item', 'item_id' => $this->chair->id, 'min_quantity' => '1', 'method' => 'fixed', 'value' => $price]],
        ])->assertCreated()->json('data.id');
    }

    private function chairPrice(): ?string
    {
        return $this->postJson('/api/v1/sales/prices', [
            'currency' => 'USD', 'date' => '2026-10-28', 'contact_id' => $this->customer->id,
            'lines' => [['key' => 'a', 'item_id' => $this->chair->id, 'quantity' => '1']],
        ])->assertOk()->json('data.0.unit_price');
    }

    public function test_the_full_apps_settings_are_kept_and_reach_every_page(): void
    {
        // Sales Lite has none of them.
        $this->assertArrayNotHasKey('new_sales_start_as', $this->getJson('/api/v1/business-settings/sales')->assertOk()->json('data'));
        $this->getJson('/api/v1/navigation')->assertOk()->assertJsonPath('data.workspace.sales', null);

        $this->full();
        $list = $this->list('Wholesale', '120');

        $this->settings([
            'new_sales_start_as' => 'quotation', 'default_price_list_id' => $list, 'deposit_percent' => '30', 'backorders' => 'always',
            'default_shipping_terms' => 'DAP', 'salesperson_is_creator' => true,
        ])->assertOk()
            ->assertJsonPath('data.new_sales_start_as', 'quotation')
            ->assertJsonPath('data.default_price_list', ['id' => $list, 'name' => 'Wholesale'])
            ->assertJsonPath('data.deposit_percent', '30.00')
            ->assertJsonPath('data.backorders', 'always')
            ->assertJsonPath('data.default_shipping_terms', 'DAP')
            ->assertJsonPath('data.salesperson_is_creator', true);

        $this->getJson('/api/v1/navigation')->assertOk()->assertJsonPath('data.workspace.sales', ['start_as' => 'quotation', 'deposit_percent' => '30.00', 'backorders' => 'always']);

        // A new quotation starts with the shipping terms and its maker as salesperson.
        $this->getJson('/api/v1/sales/quotations/defaults')->assertOk()->assertJsonPath('data.shipping_terms', 'DAP')->assertJsonPath('data.salesperson_id', $this->user->id);

        $this->settings(['deposit_percent' => '0'])->assertJsonValidationErrors('deposit_percent');
        $this->settings(['deposit_percent' => '150'])->assertJsonValidationErrors('deposit_percent');
        $this->settings(['backorders' => 'sometimes'])->assertJsonValidationErrors('backorders');
        $this->settings(['new_sales_start_as' => 'proforma'])->assertJsonValidationErrors('new_sales_start_as');

        // Another business's list is not one of ours.
        $theirs = PriceList::forceCreate([
            'workspace_id' => $this->businessOf(User::factory()->create())->id, 'name' => 'Theirs', 'currency' => 'USD',
            'is_active' => true, 'all_customers' => true, 'all_locations' => true, 'priority' => 1,
        ])->id;
        $this->settings(['default_price_list_id' => $theirs])->assertJsonValidationErrors(['default_price_list_id' => 'Choose one of your price lists.']);
    }

    public function test_the_default_price_list_prices_what_no_other_list_does(): void
    {
        $this->full();

        // For someone else only - but as the default, it is tried for anyone, after the others.
        $default = $this->list('Fallback', '125', allCustomers: false);
        $this->assertNull($this->chairPrice());

        $this->settings(['default_price_list_id' => $default])->assertOk();
        $this->assertSame('125.00', $this->chairPrice());

        // A list that holds comes first.
        $this->list('Wholesale', '120');
        $this->assertSame('120.00', $this->chairPrice());
    }

    public function test_a_partial_delivery_makes_a_backorder_as_the_rule_says(): void
    {
        $this->full();
        $order = $this->draft('orders');
        $this->postJson("/api/v1/sales/orders/{$order}/confirm")->assertOk();
        [$chair] = array_column($this->getJson("/api/v1/sales/orders/{$order}")->json('data.lines'), 'id');

        $delivery = fn () => $this->postJson("/api/v1/sales/orders/{$order}/deliveries", ['lines' => [['line_id' => $chair, 'quantity' => '1']]])->assertCreated()->json('data.id');

        // Never: asked for, none is made.
        $this->settings(['backorders' => 'never'])->assertOk();
        $this->postJson('/api/v1/sales/deliveries/'.$delivery().'/deliver', ['backorder' => true])->assertOk();
        $this->assertSame(0, SalesDelivery::whereNotNull('backorder_of')->count());

        // Always: not asked for, one is made.
        $this->settings(['backorders' => 'always'])->assertOk();
        $first = $delivery();
        $this->postJson("/api/v1/sales/deliveries/{$first}/deliver", ['backorder' => false])->assertOk();
        $this->assertSame(1, SalesDelivery::where('backorder_of', $first)->count());
    }

    public function test_the_dashboard_shows_the_sales_pipeline_on_the_full_app(): void
    {
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.sales.pipeline', null);

        $this->full();

        // A quotation sent and awaiting an answer, and a confirmed order: 335.50 each.
        $quotation = $this->draft('quotations');
        $this->postJson("/api/v1/sales/quotations/{$quotation}/send")->assertOk();
        $order = $this->draft('orders');
        $this->postJson("/api/v1/sales/orders/{$order}/confirm")->assertOk();

        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.sales.pipeline', [
            'awaiting'   => ['count' => 1, 'value' => '335.50'],
            'confirmed'  => ['count' => 1, 'value' => '335.50'],
            'to_deliver' => ['count' => 1, 'value' => '335.50'],
            'to_invoice' => ['count' => 1, 'value' => '335.50'],
        ]);

        // Answered, the quotation no longer waits.
        $this->postJson("/api/v1/sales/quotations/{$quotation}/accept")->assertOk();
        $this->getJson('/api/v1/dashboard')->assertJsonPath('data.sales.pipeline.awaiting', ['count' => 0, 'value' => '0.00']);
    }
}
