<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase F2: a role's largest discount - past it, "Discount Beyond the
 * Limit" and a reason the document keeps - and a price typed by hand on an
 * item's line needing "Change Prices by Hand"; the owner and Sales Lite free
 * of both. The chair sells at USD 150. Wednesday 28 October 2026.
 */
class DiscountLimitsTest extends TestCase
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
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
    }

    /** A member whose role gives discounts up to 10% and holds $extra besides drafting invoices. */
    private function member(array $extra = []): WorkspaceRole
    {
        $member = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Counter', 'max_discount' => '10', 'permissions' => [
            'contacts.view', 'items.view', 'sales.invoices.view', 'sales.invoices.create', 'sales.invoices.edit', 'sales.values.view', ...$extra,
        ]]);
        $this->business->members()->create(['user_id' => $member->id, 'status' => 'active', 'role_id' => $role->id]);
        Sanctum::actingAs($member);

        return $role;
    }

    private function chair(array $fields): array
    {
        return $this->invoice(['lines' => [['item_id' => $this->chair->id, 'name' => 'Office Chair', 'quantity' => '2', 'unit_price' => '150.00', ...$fields]]]);
    }

    public function test_a_discount_past_the_roles_limit_needs_the_permission_and_a_reason(): void
    {
        // The owner has no limit.
        $this->postJson('/api/v1/sales/invoices', $this->chair(['discount_type' => 'percent', 'discount_value' => '40']))->assertCreated();

        $role = $this->member(['sales.pricing.override']);

        $this->postJson('/api/v1/sales/invoices', $this->chair(['discount_type' => 'percent', 'discount_value' => '10']))->assertCreated();
        $this->postJson('/api/v1/sales/invoices', $this->chair(['discount_type' => 'percent', 'discount_value' => '15']))
            ->assertUnprocessable()->assertJsonPath('errors.discount_reason.0', 'Your role gives discounts up to 10.00%. A larger one needs "Discount Beyond the Limit".');
        // 45 off 300 is 15% of the whole document.
        $this->postJson('/api/v1/sales/invoices', [...$this->chair([]), 'discount_type' => 'amount', 'discount_value' => '45'])->assertUnprocessable()->assertJsonValidationErrors('discount_reason');

        $role->update(['permissions' => [...$role->permissions, 'sales.pricing.beyond']]);

        $this->postJson('/api/v1/sales/invoices', $this->chair(['discount_type' => 'percent', 'discount_value' => '15']))
            ->assertUnprocessable()->assertJsonPath('errors.discount_reason.0', 'Your role gives discounts up to 10.00%. Say why this one is larger.');
        $this->postJson('/api/v1/sales/invoices', [...$this->chair(['discount_type' => 'percent', 'discount_value' => '15']), 'discount_reason' => 'Damaged box'])
            ->assertCreated()->assertJsonPath('data.discount_reason', 'Damaged box');
    }

    public function test_a_price_typed_by_hand_needs_the_permission(): void
    {
        $role = $this->member();

        $this->postJson('/api/v1/sales/invoices', $this->chair([]))->assertCreated();
        $this->postJson('/api/v1/sales/invoices', $this->chair(['unit_price' => '140.00']))
            ->assertUnprocessable()->assertJsonValidationErrors(['lines.0.unit_price' => 'Only someone who may change prices by hand sets another price than 150.00.']);

        $this->getJson('/api/v1/sales/invoices/defaults')->assertJsonPath('data.max_discount', '10.00')->assertJsonPath('data.prices_by_hand', false);

        $role->update(['permissions' => [...$role->permissions, 'sales.pricing.override']]);
        $this->postJson('/api/v1/sales/invoices', $this->chair(['unit_price' => '140.00']))->assertCreated();
    }

    public function test_sales_lite_keeps_prices_and_discounts_free(): void
    {
        $this->postJson('/api/v1/apps/sales/deactivate')->assertOk();
        $this->member();

        $this->postJson('/api/v1/sales/invoices', $this->chair(['unit_price' => '140.00', 'discount_type' => 'percent', 'discount_value' => '30']))->assertCreated();
    }
}
