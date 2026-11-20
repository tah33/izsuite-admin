<?php

namespace Tests\Feature;

use App\Models\Admin\Language;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Location;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\Shared\DomainDns;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase A3: a storefront's domains (section 19.5) - its izSuite
 * address, and domains of the business's own, added waiting, verified by
 * their TXT record, one verified the primary one - and the names izSuite
 * answers for, asked without signing in.
 */
class EcommerceDomainsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const STOREFRONTS = '/api/v1/ecommerce/storefronts';

    private User $user;

    private Workspace $business;

    private Location $dhaka;

    private Location $chattogram;

    /** The DNS the tests speak for: name => its TXT records. */
    private DomainDns $dns;

    private string $domains;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $ecommerce = Application::create(['app_key' => 'ecommerce', 'name' => 'eCommerce', 'family_key' => 'ecommerce', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 40, 'is_active' => true, 'status' => 'available']);
        $ecommerce->packages()->attach(Package::pluck('id'));
        Language::create(['name' => 'English', 'code' => 'en', 'native_name' => 'English', 'is_default' => true, 'is_active' => true]);

        config(['ecommerce.storefront_domain' => 'store.izsuite.test', 'ecommerce.cname_target' => 'domains.store.izsuite.test']);
        $this->dns = new class extends DomainDns
        {
            public array $records = [];

            public function txt(string $name): array
            {
                return $this->records[$name] ?? [];
            }
        };
        $this->app->instance(DomainDns::class, $this->dns);

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD'])->save();
        $this->business->package()->associate(Package::where('key', 'premium')->sole())->save();
        $this->dhaka      = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->chattogram = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);

        $this->postJson('/api/v1/apps/ecommerce/activate')->assertOk();
        $id = $this->postJson(self::STOREFRONTS, ['name' => 'Rahman Online', 'code' => 'rahman-online', 'default_language' => 'en', 'currency' => 'USD', 'countries' => ['BD'], 'location_id' => $this->dhaka->id])->assertCreated()->json('data.id');
        $this->domains = self::STOREFRONTS."/{$id}/domains";
    }

    /** A member whose role holds $permissions - at $locations only, when given. */
    private function member(array $permissions, ?array $locations = null): User
    {
        $user   = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $member = $this->business->members()->create([
            'user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id,
            'location_access' => $locations === null ? WorkspaceMember::ALL_LOCATIONS : WorkspaceMember::SELECTED_LOCATIONS,
        ]);
        $member->locations()->sync($locations ?? []);

        return $user;
    }

    public function test_a_domain_is_added_waiting_and_verified_by_its_txt_record(): void
    {
        $this->getJson($this->domains)->assertOk()->assertExactJson(['data' => [
            'address' => 'rahman-online.store.izsuite.test', 'primary_host' => 'rahman-online.store.izsuite.test', 'cname_target' => 'domains.store.izsuite.test', 'domains' => [],
        ]]);

        $domain = $this->postJson($this->domains, ['host' => 'Shop.Rahman.Test.'])->assertCreated()
            ->assertJsonPath('message', 'shop.rahman.test added. Give it its DNS records, then verify it.')
            ->assertJsonPath('data.host', 'shop.rahman.test')
            ->assertJsonPath('data.verified', false)
            ->assertJsonPath('data.is_primary', false)
            ->assertJsonPath('data.record.type', 'TXT')
            ->assertJsonPath('data.record.name', '_izsuite-verify.shop.rahman.test')
            ->json('data');
        $this->assertMatchesRegularExpression('/^izsuite-verify=[A-Za-z0-9]{32}$/', $domain['record']['value']);
        $this->assertTrue(ActivityLog::query()->where('description', 'Added the domain shop.rahman.test to the storefront "Rahman Online" (rahman-online)')->exists());

        // Never izSuite's own, another storefront's, or one written as a link.
        $this->postJson($this->domains, ['host' => 'mine.store.izsuite.test'])->assertUnprocessable()
            ->assertJsonValidationErrors(['host' => "This is under izSuite's own domain: the storefront has its izSuite address already."]);
        $this->postJson($this->domains, ['host' => 'shop.rahman.test'])->assertUnprocessable()->assertJsonValidationErrors(['host' => 'Another storefront has this domain.']);
        $this->postJson($this->domains, ['host' => 'https://shop.example.com/'])->assertUnprocessable()
            ->assertJsonValidationErrors(['host' => 'Give a domain such as shop.example.com - without https:// or a path.']);

        // Verified once its TXT record is there - among the others the name has.
        $verify = "{$this->domains}/{$domain['id']}/verify";
        $this->postJson($verify)->assertStatus(409)->assertJsonPath('message', 'shop.rahman.test is not verified yet: its TXT record _izsuite-verify.shop.rahman.test was not found. A DNS change can take some hours to reach everywhere - try again later.');
        $this->assertNotNull($this->getJson($this->domains)->json('data.domains.0.checked_at'));

        $this->dns->records['_izsuite-verify.shop.rahman.test'] = ['google-site-verification=abc', $domain['record']['value']];
        $this->postJson($verify)->assertOk()->assertJsonPath('message', 'shop.rahman.test is verified.')->assertJsonPath('data.verified', true);
        $this->assertTrue(ActivityLog::query()->where('description', 'Verified the domain shop.rahman.test of the storefront "Rahman Online" (rahman-online)')->exists());
    }

    public function test_a_verified_domain_is_the_primary_one_and_izsuite_answers_for_its_names(): void
    {
        $shop = $this->postJson($this->domains, ['host' => 'shop.rahman.test'])->assertCreated()->json('data');
        $www  = $this->postJson($this->domains, ['host' => 'www.rahman.test'])->assertCreated()->json('data');
        $this->dns->records[$shop['record']['name']] = [$shop['record']['value']];
        $this->postJson("{$this->domains}/{$shop['id']}/verify")->assertOk();

        // Only a verified domain is the primary one: the others are reached through it.
        $this->postJson("{$this->domains}/primary", ['domain_id' => $www['id']])->assertStatus(409)
            ->assertJsonPath('message', 'Verify www.rahman.test first: only a verified domain becomes the primary one.');
        $this->postJson("{$this->domains}/primary", ['domain_id' => $shop['id']])->assertOk()
            ->assertJsonPath('message', 'Primary domain saved.')
            ->assertJsonPath('data.primary_host', 'shop.rahman.test')
            ->assertJsonPath('data.domains.0.is_primary', true);
        $this->assertTrue(ActivityLog::query()->where('description', 'Made shop.rahman.test the primary domain of the storefront "Rahman Online" (rahman-online)')->exists());

        // Without signing in: its izSuite address and its verified domain - not a waiting one, nor a name under another.
        $this->app['auth']->forgetGuards();
        $ask = fn (string $name) => $this->getJson('/api/v1/storefront-hosts?domain='.urlencode($name));
        $ask('shop.rahman.test')->assertOk()->assertExactJson(['data' => ['code' => 'rahman-online', 'status' => 'draft', 'primary_host' => 'shop.rahman.test']]);
        $ask('Rahman-Online.store.izsuite.test.')->assertOk()->assertJsonPath('data.primary_host', 'shop.rahman.test');
        $ask('www.rahman.test')->assertNotFound()->assertJsonPath('message', 'izSuite has no storefront at this name.');
        $ask('a.rahman-online.store.izsuite.test')->assertNotFound();
        $ask('nobody.store.izsuite.test')->assertNotFound();

        // Removed, the primary one leaves the izSuite address in its place.
        Sanctum::actingAs($this->user);
        $this->deleteJson("{$this->domains}/{$shop['id']}")->assertOk()->assertJsonPath('message', 'shop.rahman.test removed.');
        $this->getJson($this->domains)->assertJsonPath('data.primary_host', 'rahman-online.store.izsuite.test')->assertJsonCount(1, 'data.domains');
        $this->postJson("{$this->domains}/primary", ['domain_id' => null])->assertOk()->assertJsonPath('data.primary_host', 'rahman-online.store.izsuite.test');
        $this->assertTrue(ActivityLog::query()->where('description', 'Removed the domain shop.rahman.test from the storefront "Rahman Online" (rahman-online)')->exists());
    }

    public function test_domains_are_seen_with_the_storefront_and_changed_under_manage_domains(): void
    {
        $this->assertEqualsCanonicalizing(['ecommerce.domains.manage', 'ecommerce.store.view'], Permissions::expand(['ecommerce.domains.manage']));

        // Viewing eCommerce: the domains, without changes.
        Sanctum::actingAs($this->member(['ecommerce.store.view']));
        $this->getJson($this->domains)->assertOk();
        $this->postJson($this->domains, ['host' => 'shop.rahman.test'])->assertForbidden();

        // Managing domains: those of the storefronts at one's own locations.
        Sanctum::actingAs($this->member(['ecommerce.store.view', 'ecommerce.domains.manage'], [$this->chattogram->id]));
        $this->getJson($this->domains)->assertNotFound();
        $this->postJson($this->domains, ['host' => 'shop.rahman.test'])->assertNotFound();

        Sanctum::actingAs($this->member(['ecommerce.store.view', 'ecommerce.domains.manage'], [$this->dhaka->id]));
        $this->postJson($this->domains, ['host' => 'shop.rahman.test'])->assertCreated();
    }
}
