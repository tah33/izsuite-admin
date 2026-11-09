<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Shared\ActivityLog;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\Documents\DocumentRenderer;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Plans and the subscription page (Doc 13 sections 2, 11 and 18) - Doc 13
 * Phase B1: a plan's prices, trial, grace and branding are set in the
 * admin; a business sees its plan and status, the plans to compare and its
 * billing details; "Powered by izSuite" follows the plan, and a document
 * keeps what it was issued with.
 */
class SubscriptionBillingTest extends TestCase
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
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));

        $this->offerSalesLite();
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->setUpSales();
    }

    private function onPlan(string $key): void
    {
        $this->business->package()->associate(Package::where('key', $key)->sole())->save();
    }

    private function footer(int $id): string
    {
        return app(DocumentRenderer::class)->sales($this->business->fresh(), SalesDocument::findOrFail($id))->footer;
    }

    public function test_a_plan_is_priced_in_the_admin(): void
    {
        $standard = Package::where('key', 'standard')->sole();
        $this->actingAs(User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]));

        // The admin's currencies (Admin > Currencies): the form lists the active ones.
        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_active' => true]);
        Currency::create(['name' => 'Bangladeshi Taka', 'code' => 'BDT', 'symbol' => '৳', 'exchange_rate' => 110, 'is_active' => true]);

        $this->get(route('admin.packages.edit', $standard->id))->assertOk()->assertSee('Price a month')->assertSee('Takes "Powered by izSuite" off documents')->assertSee('Bangladeshi Taka (BDT)');
        $this->putJson(route('admin.packages.update', $standard->id), ['name' => 'Standard', 'price_currency' => 'XYZ'])->assertJsonValidationErrors('price_currency');
        $this->get(route('admin.packages.index'))->assertOk()->assertSee('Not announced');

        $this->putJson(route('admin.packages.update', $standard->id), [
            'name' => 'Standard', 'price_currency' => 'usd', 'price_monthly' => '19.50', 'price_yearly' => '195', 'trial_days' => 14, 'grace_days' => 7, 'removes_branding' => '1', 'is_active' => '1',
        ])->assertOk();

        $standard->refresh();
        $this->assertSame(['USD', '19.50', '195.00', 14, 7, true], [$standard->price_currency, $standard->price_monthly, $standard->price_yearly, $standard->trial_days, $standard->grace_days, $standard->removes_branding]);
        $this->get(route('admin.packages.index'))->assertSee('USD 19.50 a month')->assertSee('USD 195.00 a year');

        $this->putJson(route('admin.packages.update', $standard->id), ['name' => 'Standard', 'price_currency' => 'USD', 'price_monthly' => '-1', 'trial_days' => 0])
            ->assertJsonValidationErrors(['price_monthly', 'trial_days']);

        // The website's Pricing page has them.
        $this->app['auth']->forgetGuards();
        $offer    = collect($this->getJson('/api/v1/catalog')->json('data.packages'))->firstWhere('key', 'standard');
        $this->assertSame(['19.50', '195.00', 14, false], [$offer['price_monthly'], $offer['price_yearly'], $offer['trial_days'], $offer['free']]);
    }

    public function test_the_subscription_shows_the_plan_and_the_plans_to_compare(): void
    {
        $page   = $this->getJson('/api/v1/subscription')->assertOk()->json('data');

        $this->assertSame(['Billing Starter', 'free', null], [$page['package']['name'], $page['status'], $page['details']]);
        $this->assertSame(['starter', 'standard', 'premium'], array_column($page['packages'], 'key'));
        $this->assertSame([true, false, false], array_column($page['packages'], 'current'));
        $this->assertSame([true, '0.00'], [$page['packages'][0]['free'], $page['packages'][0]['price_monthly']]);

        $this->onPlan('standard');
        $this->assertSame(['Standard', 'active'], [$this->getJson('/api/v1/subscription')->json('data.package.name'), $this->getJson('/api/v1/subscription')->json('data.status')]);

        // Billing is for those who manage the subscription (Doc 13 section 21).
        $role   = $this->business->roles()->create(['name' => 'Settings Viewer', 'permissions' => Permissions::expand(['settings.view'])]);
        $member = User::factory()->create();
        $this->business->members()->create(['user_id' => $member->id, 'role_id' => $role->id, 'name' => $member->name, 'email' => $member->email, 'status' => 'active']);
        Sanctum::actingAs($member);
        $this->getJson('/api/v1/subscription', $this->pick($this->business))->assertForbidden();
    }

    public function test_billing_details_are_saved_and_logged(): void
    {
        $details = ['name' => 'Uddin Traders Ltd', 'email' => 'accounts@uddin.example', 'address' => "House 12, Road 5\nDhaka 1207", 'country' => 'bd', 'tax_id' => 'BIN-778899'];

        $this->putJson('/api/v1/subscription/billing-details', $details)
            ->assertOk()
            ->assertJsonPath('message', 'Billing details saved.')
            ->assertJsonPath('data.country', 'BD');

        $this->assertSame('accounts@uddin.example', $this->getJson('/api/v1/subscription')->json('data.details.email'));
        $this->assertSame('Changed the billing details', ActivityLog::where('description', 'Changed the billing details')->latest('id')->value('description'));

        $this->putJson('/api/v1/subscription/billing-details', [...$details, 'country' => 'XX', 'email' => 'not an e-mail'])->assertJsonValidationErrors(['country', 'email']);
        $this->putJson('/api/v1/subscription/billing-details', [...$details, 'tax_id' => null])->assertOk()->assertJsonPath('data.tax_id', null);
    }

    public function test_branding_follows_the_plan_and_stays_with_issued_documents(): void
    {
        $before = $this->issuedInvoice();
        $this->assertStringContainsString('Powered by izSuite', $this->footer($before));

        // A plan that takes it off: new documents go without, the one issued before keeps it.
        $this->onPlan('standard');
        $after  = $this->issuedInvoice();
        $draft  = $this->draft('quotations');

        $this->assertStringNotContainsString('Powered by izSuite', $this->footer($after));
        $this->assertStringNotContainsString('Powered by izSuite', $this->footer($draft));
        $this->assertStringContainsString('Powered by izSuite', $this->footer($before));

        // And back on Billing Starter, what was issued without it stays without it.
        $this->onPlan('starter');
        $this->assertStringNotContainsString('Powered by izSuite', $this->footer($after));
        $this->assertStringContainsString('Powered by izSuite', $this->footer($draft));
    }
}
