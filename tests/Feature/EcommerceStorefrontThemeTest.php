<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\Storefront;
use App\Models\User\User;
use App\Models\User\WorkspaceFile;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use App\Support\StorefrontTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase C4a: a storefront's theme and branding (section 19.7) - a
 * draft its team changes and previews on the storefront itself, published
 * for its customers or discarded, its logo and favicon among its own
 * pictures, and "Powered by izSuite" as the plan allows - under Manage
 * Themes and Pages.
 */
class EcommerceStorefrontThemeTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private string $theme;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00']]);
        $this->listed('Ceramic Mug');
        $this->theme = '/api/v1/ecommerce/storefronts/'.$this->store->id.'/theme';
    }

    private function draft(array $fields = []): array
    {
        return array_replace([
            'logo'    => null,
            'favicon' => null,
            'colors'  => ['primary' => '#AA3300', 'background' => '#ffffff', 'text' => '#111111'],
            'font'    => 'serif',
            'header'  => ['layout' => 'centered', 'announcement' => ' Free delivery over $50 '],
            'card'    => ['style' => 'minimal', 'image' => 'portrait'],
            'footer'  => ['text' => 'Since 1990.'],
            'contact' => ['address' => 'Road 7, Dhaka', 'hours' => 'Every day, 10 to 8'],
            'social'  => [['network' => 'facebook', 'url' => 'https://facebook.com/rahman'], ['network' => 'facebook', 'url' => 'https://facebook.com/another']],
        ], $fields);
    }

    /** @return list<int> the pictures' ids */
    private function pictures(int $count = 1): array
    {
        $files = array_map(fn (int $n) => UploadedFile::fake()->image("picture-{$n}.png", 300, 100), range(1, $count));

        return $this->post($this->theme.'/pictures', ['files' => $files], ['Accept' => 'application/json'])->assertCreated()->json('data.*.id');
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_a_theme_is_drafted_previewed_published_and_discarded(): void
    {
        $this->getJson($this->theme)->assertOk()
            ->assertJsonPath('data.draft', StorefrontTheme::DEFAULTS)
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.published_at', null);

        [$logo] = $this->pictures();
        $this->putJson($this->theme, $this->draft(['logo' => $logo]))->assertOk()
            ->assertJsonPath('message', 'Draft saved.')
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.draft.colors.primary', '#aa3300')
            ->assertJsonPath('data.draft.header.announcement', 'Free delivery over $50')
            ->assertJsonPath('data.draft.social', [['network' => 'facebook', 'url' => 'https://facebook.com/rahman']]);
        $this->assertTrue(ActivityLog::query()->where('description', 'Updated the draft theme of the storefront "Rahman Shop"')->exists());

        // Its customers see the theme published - the defaults still; its team, asking for a preview, the draft.
        $this->getJson(self::STORE)->assertJsonPath('data.preview', false)->assertJsonPath('data.theme.font', 'system')->assertJsonPath('data.theme.logo_url', null);
        $this->getJson(self::STORE.'?preview=1')->assertOk()
            ->assertJsonPath('data.preview', true)
            ->assertJsonPath('data.open_to_customers', true)
            ->assertJsonPath('data.theme.font', 'serif')
            ->assertJsonPath('data.theme.card', ['style' => 'minimal', 'image' => 'portrait']);
        $this->assertNotNull($this->getJson(self::STORE.'?preview=1')->json('data.theme.logo_url'));

        $this->postJson($this->theme.'/publish')->assertOk()->assertJsonPath('message', 'Theme published.')->assertJsonPath('data.changed', false);
        $this->assertNotNull($this->getJson($this->theme)->json('data.published_at'));
        $this->getJson(self::STORE)->assertJsonPath('data.theme.font', 'serif')->assertJsonPath('data.theme.contact.address', 'Road 7, Dhaka');

        // A new draft discarded: the published theme back in its place, and the picture only it showed gone.
        [$other] = $this->pictures();
        $stored = WorkspaceFile::findOrFail($other)->path;
        $this->putJson($this->theme, $this->draft(['logo' => $other, 'font' => 'sans']))->assertOk()->assertJsonPath('data.changed', true);
        $this->postJson($this->theme.'/discard')->assertOk()
            ->assertJsonPath('message', 'Draft discarded.')
            ->assertJsonPath('data.draft.font', 'serif')
            ->assertJsonPath('data.draft.logo', $logo)
            ->assertJsonPath('data.changed', false);
        $this->assertNull(WorkspaceFile::find($other));
        Storage::disk('public')->assertMissing($stored);
        $this->assertNotNull(WorkspaceFile::find($logo));
    }

    public function test_the_theme_is_checked(): void
    {
        $this->putJson($this->theme, $this->draft(['colors' => ['primary' => 'red', 'background' => '#ffffff', 'text' => '#111111']]))
            ->assertJsonValidationErrors(['colors.primary' => 'Give the colour as # and six hex digits, such as #1769e8.']);
        $this->putJson($this->theme, $this->draft(['font' => 'comic']))->assertJsonValidationErrors('font');
        $this->putJson($this->theme, $this->draft(['social' => [['network' => 'x', 'url' => 'http://x.com/rahman']]]))->assertJsonValidationErrors(['social.0.url' => 'Give the link starting https://.']);
        $this->putJson($this->theme, $this->draft(['header' => ['layout' => 'left', 'announcement' => str_repeat('a', 161)]]))->assertJsonValidationErrors(['header.announcement' => 'Keep the announcement to 160 characters.']);
        $this->putJson($this->theme, $this->draft(['logo' => 999999]))->assertJsonValidationErrors(['logo' => 'Choose a picture uploaded for this storefront.']);

        // Up to twenty pictures of its own.
        $this->pictures(10);
        $this->pictures(10);
        $this->post($this->theme.'/pictures', ['files' => [UploadedFile::fake()->image('one-more.png')]], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['files' => 'A storefront keeps up to 20 pictures: publish or discard the draft to clear those it does not show.']);
    }

    public function test_a_store_not_yet_live_is_previewed_by_its_team_alone(): void
    {
        $this->store->update(['status' => Storefront::DRAFT]);

        $this->getJson(self::STORE.'?preview=1')->assertOk()->assertJsonPath('data.open', true)->assertJsonPath('data.preview', true)->assertJsonPath('data.open_to_customers', false);
        $this->getJson(self::STORE.'/products?preview=1')->assertOk()->assertJsonPath('data.0.title', 'Ceramic Mug');
        $this->getJson(self::STORE.'/products')->assertNotFound();

        // One who may only view eCommerce previews nothing, and may not open the theme.
        Sanctum::actingAs($this->member(Permissions::expand(['ecommerce.store.view'])));
        $this->getJson(self::STORE.'?preview=1')->assertJsonPath('data.open', false);
        $this->getJson($this->theme)->assertForbidden();

        Sanctum::actingAs($this->member(Permissions::expand(['ecommerce.themes.manage'])));
        $this->getJson(self::STORE.'?preview=1')->assertJsonPath('data.preview', true);
        $this->getJson($this->theme)->assertOk();
        $this->assertEqualsCanonicalizing(['ecommerce.store.view', 'ecommerce.themes.manage'], Permissions::expand(['ecommerce.themes.manage']));
    }

    public function test_powered_by_izsuite_follows_the_plan(): void
    {
        $this->business->package->forceFill(['removes_branding' => false])->save();
        $this->getJson(self::STORE)->assertJsonPath('data.theme.powered_by', true);

        $this->business->package->forceFill(['removes_branding' => true])->save();

        $this->getJson(self::STORE)->assertJsonPath('data.theme.powered_by', false);
        $this->getJson($this->theme)->assertJsonPath('data.powered_by', false);
    }
}
