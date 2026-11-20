<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\PackageVersion;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase C5b: pages of the business's own on a storefront (section
 * 19.6: "according to package limits") - added, changed and deleted under
 * Manage Themes and Pages, each at the link it is given or one made from its
 * title, in its place in the footer - up to the plan's limit on each
 * storefront, as the admin sets it.
 */
class EcommerceStorefrontOwnPagesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private string $pages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00']]);
        $this->pages = '/api/v1/ecommerce/storefronts/'.$this->store->id.'/pages';
    }

    private function page(string $title, array $fields = []): array
    {
        return ['title' => $title, 'body' => "<p>{$title}: our words.</p>", 'status' => 'published', 'in_footer' => true, 'seo_title' => null, 'seo_description' => null, ...$fields];
    }

    private function logged(string $description): bool
    {
        return ActivityLog::query()->where('description', $description)->exists();
    }

    public function test_pages_of_its_own_are_added_changed_and_deleted(): void
    {
        $wholesale = $this->postJson($this->pages, $this->page('Wholesale'))->assertCreated()
            ->assertJsonPath('message', 'Page added.')
            ->assertJsonPath('data.page.kind', 'page')
            ->assertJsonPath('data.page.slug', 'wholesale')
            ->assertJsonPath('data.page.position', 1)
            ->json('data.page.id');
        $this->assertTrue($this->logged('Published the page "Wholesale" of the storefront "Rahman Shop"'));

        // A link made from its title, numbered past the standard pages' and the others'; one given, checked.
        $this->postJson($this->pages, $this->page('About', ['status' => 'draft']))->assertCreated()->assertJsonPath('data.page.slug', 'about-2');
        $this->postJson($this->pages, $this->page('Wholesale', ['status' => 'draft']))->assertCreated()->assertJsonPath('data.page.slug', 'wholesale-2');
        $this->postJson($this->pages, $this->page('Policy', ['slug' => 'Privacy-Policy']))->assertJsonValidationErrors(['slug' => 'This link is kept for one of its standard pages: choose another.']);
        $this->postJson($this->pages, $this->page('Trade', ['slug' => 'wholesale']))->assertJsonValidationErrors(['slug' => 'Another page of this storefront has this link: choose another.']);
        $this->postJson($this->pages, $this->page('Trade', ['slug' => 'trade prices']))->assertJsonValidationErrors(['slug' => 'Use letters, digits and dashes, starting and ending with a letter or digit.']);

        // At its link; in the footer after the standard pages, by its place.
        $this->putJson($this->pages.'/privacy', $this->page('Privacy Policy'))->assertOk();
        $careers = $this->postJson($this->pages, $this->page('Careers', ['position' => 0]))->assertCreated()->json('data.page.id');
        $this->getJson(self::STORE.'/pages/wholesale')->assertOk()->assertJsonPath('data', [
            'kind'    => 'page',
            'title'   => 'Wholesale',
            'body'    => '<p>Wholesale: our words.</p>',
            'contact' => null,
            'seo'     => ['title' => 'Wholesale', 'description' => null],
        ]);
        $this->getJson(self::STORE)->assertJsonPath('data.pages', [['title' => 'Privacy Policy', 'slug' => 'privacy-policy'], ['title' => 'Careers', 'slug' => 'careers'], ['title' => 'Wholesale', 'slug' => 'wholesale']]);
        $this->getJson($this->pages)->assertOk()
            ->assertJsonPath('data.own.*.title', ['Careers', 'Wholesale', 'About', 'Wholesale'])
            ->assertJsonPath('data.limit', ['used' => 4, 'limit' => null]);

        // Its link changed: found at the new one alone. Saved with none given, it keeps the one it has.
        $this->putJson($this->pages."/{$wholesale}", $this->page('Wholesale', ['slug' => 'trade']))->assertOk()->assertJsonPath('data.page.slug', 'trade');
        $this->getJson(self::STORE.'/pages/wholesale')->assertNotFound();
        $this->putJson($this->pages."/{$wholesale}", $this->page('Trade Prices'))->assertOk()->assertJsonPath('data.page.slug', 'trade');
        $this->getJson(self::STORE.'/pages/trade')->assertOk()->assertJsonPath('data.title', 'Trade Prices');

        // Deleted: gone from its link and the footer.
        $this->deleteJson($this->pages."/{$careers}")->assertOk()->assertJsonPath('message', 'Page deleted.');
        $this->assertTrue($this->logged('Deleted the page "Careers" of the storefront "Rahman Shop"'));
        $this->getJson(self::STORE.'/pages/careers')->assertNotFound();
        $this->deleteJson($this->pages."/{$careers}")->assertNotFound();
        $this->getJson($this->pages.'/999999')->assertNotFound();
    }

    public function test_the_plan_limits_the_pages_of_its_own_on_each_storefront(): void
    {
        $this->assertSame(['starter' => 3, 'standard' => null, 'premium' => null], Package::orderBy('rank')->pluck('storefront_page_limit', 'key')->all());
        $this->assertContains('storefront_page_limit', PackageVersion::TERMS);

        $this->business->package->forceFill(['storefront_page_limit' => 2])->save();
        $this->postJson($this->pages, $this->page('One'))->assertCreated();
        $this->postJson($this->pages, $this->page('Two'))->assertCreated();
        $this->postJson($this->pages, $this->page('Three'))->assertForbidden()
            ->assertJsonPath('message', 'Premium includes 2 pages of your own on each storefront. Upgrade the plan to add another.');
        $this->getJson($this->pages)->assertJsonPath('data.limit', ['used' => 2, 'limit' => 2]);

        // Its standard pages are outside the limit.
        $this->putJson($this->pages.'/about', $this->page('About Us'))->assertOk();

        // One who may only view eCommerce adds none.
        $member = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Viewer', 'permissions' => Permissions::expand(['ecommerce.store.view'])]);
        $this->business->members()->create(['user_id' => $member->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);
        Sanctum::actingAs($member);
        $this->postJson($this->pages, $this->page('Four'))->assertForbidden()->assertJsonPath('message', 'Your role does not allow this.');
    }

    public function test_the_admin_sets_a_plans_pages_per_storefront_and_the_plans_say_them(): void
    {
        $admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        $plan  = Package::where('key', 'premium')->sole();
        $save  = fn (array $data) => $this->actingAs($admin)->putJson(route('admin.packages.update', $plan->id), ['name' => $plan->name, 'is_active' => 1, 'removes_branding' => (int) $plan->removes_branding, 'offers_monthly' => 1, ...$data]);

        $save(['storefront_page_limit' => 0])->assertUnprocessable()->assertJsonValidationErrors(['storefront_page_limit' => 'Enter a whole number of at least 1, or leave it empty for no limit.']);
        $save(['storefront_page_limit' => 5]);
        $this->assertSame(5, $plan->fresh()->storefront_page_limit);
        $this->assertSame(5, PackageVersion::where('package_id', $plan->id)->latest('id')->value('storefront_page_limit'));
        $this->actingAs($admin)->get(route('admin.packages.index'))->assertOk()->assertSee('5 pages per storefront');

        Sanctum::actingAs($this->user);
        $plans = collect($this->getJson('/api/v1/catalog')->assertOk()->json('data.packages'))->keyBy('key');
        $this->assertSame([3, 5], [$plans['starter']['storefront_page_limit'], $plans['premium']['storefront_page_limit']]);
    }
}
