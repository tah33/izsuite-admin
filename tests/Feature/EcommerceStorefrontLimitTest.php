<?php

namespace Tests\Feature;

use App\Models\Admin\Language;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\PackageVersion;
use App\Models\Frontend\Application;
use App\Models\User\Location;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase A2: how many storefronts a plan gives a business (section
 * 19.4) - as the admin sets it, empty for no limit, Billing Starter starting
 * at one - with the archived ones outside it. Adding past it, or opening an
 * archived one again, is refused; Settings shows it in Usage and Limits, and
 * the plans say it where they have eCommerce.
 */
class EcommerceStorefrontLimitTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const STOREFRONTS = '/api/v1/ecommerce/storefronts';

    private User $user;

    private Workspace $business;

    private Location $dhaka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $ecommerce = Application::create(['app_key' => 'ecommerce', 'name' => 'eCommerce', 'family_key' => 'ecommerce', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 40, 'is_active' => true, 'status' => 'available']);
        $ecommerce->packages()->attach(Package::whereIn('key', ['starter', 'premium'])->pluck('id'));
        Language::create(['name' => 'English', 'code' => 'en', 'native_name' => 'English', 'is_default' => true, 'is_active' => true]);

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD'])->save();
        $this->dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
    }

    /** A Dhaka storefront in dollars and English, selling to Bangladesh, named $name, unless $fields say otherwise. */
    private function storefront(string $name, array $fields = []): TestResponse
    {
        return $this->postJson(self::STOREFRONTS, [
            'name' => $name, 'code' => strtolower(str_replace(' ', '-', $name)), 'default_language' => 'en', 'currency' => 'USD', 'countries' => ['BD'], 'location_id' => $this->dhaka->id, ...$fields,
        ]);
    }

    public function test_billing_starter_gives_one_storefront_and_the_paid_plans_no_limit(): void
    {
        $this->assertSame(['starter' => 1, 'standard' => null, 'premium' => null], Package::orderBy('rank')->pluck('storefront_limit', 'key')->all());
        $this->assertContains('storefront_limit', PackageVersion::TERMS);
    }

    public function test_a_business_keeps_as_many_storefronts_as_its_plan_gives_the_archived_ones_aside(): void
    {
        // Without eCommerce, Usage and Limits has no storefronts.
        $this->getJson('/api/v1/business-settings/usage')->assertOk()->assertJsonPath('data.storefronts', null);
        $this->postJson('/api/v1/apps/ecommerce/activate')->assertOk();
        $this->getJson('/api/v1/business-settings/usage')->assertJsonPath('data.storefronts', ['used' => 0, 'limit' => 1, 'state' => 'ok']);

        $first = $this->storefront('Main Shop')->assertCreated()->json('data');
        $this->getJson('/api/v1/business-settings/usage')->assertJsonPath('data.storefronts', ['used' => 1, 'limit' => 1, 'state' => 'full']);
        $this->storefront('Second Shop')->assertForbidden()
            ->assertJsonPath('message', 'Billing Starter includes one storefront. Upgrade the plan to add another.');

        // An archived one is kept outside the limit - one made archived too.
        $this->storefront('Old Shop', ['status' => 'archived'])->assertCreated();
        $this->putJson(self::STOREFRONTS."/{$first['id']}", [...$first, 'location_id' => $this->dhaka->id, 'status' => 'archived'])->assertOk();
        $this->getJson('/api/v1/business-settings/usage')->assertJsonPath('data.storefronts', ['used' => 0, 'limit' => 1, 'state' => 'ok']);
        $second = $this->storefront('Second Shop')->assertCreated()->json('data');

        // Opened again, an archived one needs room; changed without its status, it keeps it.
        $this->putJson(self::STOREFRONTS."/{$first['id']}", [...$first, 'location_id' => $this->dhaka->id, 'status' => 'live'])->assertForbidden()
            ->assertJsonPath('message', 'Billing Starter includes one storefront. Upgrade the plan to add another.');
        $this->putJson(self::STOREFRONTS."/{$second['id']}", collect($second)->except('status')->merge(['location_id' => $this->dhaka->id, 'name' => 'Second Store'])->all())->assertOk()
            ->assertJsonPath('data.status', 'draft');

        // On a plan with no limit, it may grow.
        $this->business->package()->associate(Package::where('key', 'premium')->sole())->save();
        $this->putJson(self::STOREFRONTS."/{$first['id']}", [...$first, 'location_id' => $this->dhaka->id, 'status' => 'live'])->assertOk();
        $this->getJson('/api/v1/business-settings/usage')->assertJsonPath('data.storefronts', ['used' => 2, 'limit' => null, 'state' => 'ok']);
    }

    public function test_the_admin_sets_a_plans_storefronts_and_the_plans_say_them(): void
    {
        $admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        $plan  = Package::where('key', 'premium')->sole();
        $save  = fn (array $data) => $this->actingAs($admin)->putJson(route('admin.packages.update', $plan->id), ['name' => $plan->name, 'is_active' => 1, 'removes_branding' => (int) $plan->removes_branding, 'offers_monthly' => 1, ...$data]);

        $save(['storefront_limit' => 0])->assertUnprocessable()->assertJsonValidationErrors(['storefront_limit' => 'Enter a whole number of at least 1, or leave it empty for no limit.']);
        $save(['storefront_limit' => 3]);
        $this->assertSame(3, $plan->fresh()->storefront_limit);
        $this->assertSame(3, PackageVersion::where('package_id', $plan->id)->latest('id')->value('storefront_limit'));
        $this->actingAs($admin)->get(route('admin.packages.index'))->assertOk()->assertSee('3 storefronts');

        // The plans: their storefronts where they have eCommerce.
        Sanctum::actingAs($this->user);
        $plans = collect($this->getJson('/api/v1/catalog')->assertOk()->json('data.packages'))->keyBy('key');
        $this->assertSame([true, 1], [$plans['starter']['sells_online'], $plans['starter']['storefront_limit']]);
        $this->assertSame([false, null], [$plans['standard']['sells_online'], $plans['standard']['storefront_limit']]);
        $this->assertSame([true, 3], [$plans['premium']['sells_online'], $plans['premium']['storefront_limit']]);
    }
}
