<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlansApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlans(): void
    {
        Plan::create([
            'name' => 'Free', 'slug' => 'free', 'description' => 'Starter plan.',
            'plan_for' => 'recruiter', 'billing_type' => 'monthly',
            'monthly_price' => 0, 'yearly_price' => 0, 'trial_days' => 0,
            'features' => ['Basic workspace'],
            'job_postings_limit' => 1, 'ai_screenings_limit' => 10, 'team_members_limit' => 1,
            'is_active' => true, 'is_featured' => false, 'sort_order' => 1,
        ]);

        Plan::create([
            'name' => 'Pro', 'slug' => 'pro', 'description' => 'Smart leak detection.',
            'plan_for' => 'recruiter', 'billing_type' => 'monthly',
            'monthly_price' => 9.99, 'yearly_price' => 99.99, 'trial_days' => 14,
            'features' => ['Priority support'],
            // null limits mean unlimited
            'is_active' => true, 'is_featured' => true, 'sort_order' => 2,
        ]);

        Plan::create([
            'name' => 'Yearly Only', 'slug' => 'yearly-only', 'description' => 'Annual billing.',
            'plan_for' => 'candidate', 'billing_type' => 'yearly',
            'monthly_price' => 0, 'yearly_price' => 199.99, 'trial_days' => 0,
            'is_active' => true, 'is_featured' => false, 'sort_order' => 3,
        ]);

        Plan::create([
            'name' => 'Retired', 'slug' => 'retired', 'description' => 'No longer sold.',
            'plan_for' => 'recruiter', 'billing_type' => 'monthly',
            'monthly_price' => 5, 'yearly_price' => 50,
            'is_active' => false, 'is_featured' => false, 'sort_order' => 4,
        ]);
    }

    private function names(array $json): array
    {
        return array_column($json, 'name');
    }

    /* -------------------------------------------------------------- listing */

    public function test_list_returns_active_plans_in_sort_order(): void
    {
        $this->seedPlans();

        $response = $this->getJson('/api/v1/plans');

        $response->assertOk()->assertJsonPath('pagination.total', 3);

        // Inactive plan excluded; the rest follow sort_order, not id or name.
        $this->assertSame(['Free', 'Pro', 'Yearly Only'], $this->names($response->json('data')));
    }

    public function test_plan_payload_has_the_expected_shape(): void
    {
        $this->seedPlans();

        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'name', 'slug', 'description', 'plan_for', 'billing_type',
                    'monthly_price', 'monthly_price_formatted',
                    'yearly_price', 'yearly_price_formatted',
                    'trial_days', 'features',
                    'limits'       => ['job_postings', 'ai_screenings', 'team_members'],
                    'limits_label' => ['job_postings', 'ai_screenings', 'team_members'],
                    'is_featured', 'is_active', 'sort_order', 'created_at',
                ]],
                'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_null_limits_are_reported_as_unlimited(): void
    {
        $this->seedPlans();

        $plans = collect($this->getJson('/api/v1/plans')->json('data'));

        $pro = $plans->firstWhere('name', 'Pro');
        $this->assertNull($pro['limits']['job_postings']);
        $this->assertSame('Unlimited', $pro['limits_label']['job_postings']);

        $free = $plans->firstWhere('name', 'Free');
        $this->assertSame(1, $free['limits']['job_postings']);
        $this->assertSame('10', $free['limits_label']['ai_screenings']);
    }

    public function test_prices_are_returned_raw_and_formatted(): void
    {
        // format_price() needs a currency row; without one it falls back to
        // "USD 9.99" instead of using the symbol.
        Currency::create([
            'name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$',
            'exchange_rate' => 1, 'is_default' => true, 'is_active' => true,
        ]);
        Currency::clearCache();

        $this->seedPlans();

        $pro = collect($this->getJson('/api/v1/plans')->json('data'))->firstWhere('name', 'Pro');

        $this->assertSame(9.99, $pro['monthly_price']);
        $this->assertSame('$9.99', $pro['monthly_price_formatted']);
        $this->assertSame(99.99, $pro['yearly_price']);
    }

    public function test_features_default_to_an_empty_array(): void
    {
        $this->seedPlans();

        $yearly = collect($this->getJson('/api/v1/plans')->json('data'))->firstWhere('name', 'Yearly Only');

        $this->assertSame([], $yearly['features']);
    }

    /* -------------------------------------------------------------- filters */

    public function test_featured_filter_accepts_true_and_false(): void
    {
        $this->seedPlans();

        $this->assertSame(['Pro'], $this->names($this->getJson('/api/v1/plans?featured=true')->assertOk()->json('data')));
        $this->assertSame(['Pro'], $this->names($this->getJson('/api/v1/plans?featured=1')->assertOk()->json('data')));

        $this->assertSame(
            ['Free', 'Yearly Only'],
            $this->names($this->getJson('/api/v1/plans?featured=false')->assertOk()->json('data'))
        );
    }

    public function test_blank_or_absent_featured_means_no_filter(): void
    {
        $this->seedPlans();

        // filter_var() reads '' as false, so a blank param must not filter.
        $this->assertSame(['Free', 'Pro', 'Yearly Only'], $this->names($this->getJson('/api/v1/plans?featured=')->assertOk()->json('data')));
        $this->assertSame(['Free', 'Pro', 'Yearly Only'], $this->names($this->getJson('/api/v1/plans')->assertOk()->json('data')));
    }

    public function test_unparseable_featured_value_is_rejected(): void
    {
        $this->getJson('/api/v1/plans?featured=maybe')
            ->assertStatus(422)
            ->assertJsonValidationErrors('featured');
    }

    public function test_billing_type_filter(): void
    {
        $this->seedPlans();

        $this->assertSame(['Yearly Only'], $this->names($this->getJson('/api/v1/plans?billing_type=yearly')->assertOk()->json('data')));
        $this->assertSame(['Free', 'Pro'], $this->names($this->getJson('/api/v1/plans?billing_type=monthly')->assertOk()->json('data')));
    }

    public function test_plan_for_filter(): void
    {
        $this->seedPlans();

        $this->assertSame(['Free', 'Pro'], $this->names($this->getJson('/api/v1/plans?plan_for=recruiter')->assertOk()->json('data')));
        $this->assertSame(['Yearly Only'], $this->names($this->getJson('/api/v1/plans?plan_for=candidate')->assertOk()->json('data')));
    }

    public function test_search_covers_name_and_description(): void
    {
        $this->seedPlans();

        $this->assertSame(['Pro'], $this->names($this->getJson('/api/v1/plans?search=leak')->assertOk()->json('data')));
        $this->assertSame(['Free'], $this->names($this->getJson('/api/v1/plans?search=Free')->assertOk()->json('data')));
    }

    public function test_unknown_filter_values_return_an_empty_list(): void
    {
        $this->seedPlans();

        $this->getJson('/api/v1/plans?plan_for=nobody')
            ->assertOk()
            ->assertJsonPath('pagination.total', 0)
            ->assertJsonPath('data', []);
    }

    /* ----------------------------------------------------------- validation */

    public function test_invalid_billing_type_is_rejected(): void
    {
        $this->getJson('/api/v1/plans?billing_type=weekly')
            ->assertStatus(422)
            ->assertJsonValidationErrors('billing_type');
    }

    public function test_per_page_outside_the_allowed_list_is_rejected(): void
    {
        $this->getJson('/api/v1/plans?per_page=7')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_endpoint_is_public(): void
    {
        $this->getJson('/api/v1/plans')->assertOk();
    }
}
