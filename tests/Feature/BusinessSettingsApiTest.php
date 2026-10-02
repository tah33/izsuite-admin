<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Admin\Language;
use App\Models\Shared\ActivityLog;
use App\Models\User\BusinessSetting;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private const REQUIRED_FIELDS = [
        'business_name', 'currency', 'language', 'country', 'state', 'city',
        'zip_code', 'landmark', 'timezone', 'financial_year_start_month', 'stock_accounting_method',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // EUR and French exist but an admin has switched them off.
        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);
        Currency::create(['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'is_default' => false, 'is_active' => false]);
        Language::create(['name' => 'English', 'code' => 'en', 'native_name' => 'English', 'is_default' => true, 'is_active' => true]);
        Language::create(['name' => 'French', 'code' => 'fr', 'native_name' => 'Français', 'is_default' => false, 'is_active' => false]);
    }

    /** A complete form submission, as the Settings > Business form sends it. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'business_name'              => 'Global Traders Inc.',
            'start_date'                 => '2024-03-15',
            'currency'                   => 'USD',
            'language'                   => 'en',
            'website'                    => 'https://globaltraders.example',
            'business_contact_number'    => '+880 1712-345678',
            'alternate_contact_number'   => '',
            'country'                    => 'Bangladesh',
            'state'                      => 'Dhaka',
            'city'                       => 'Dhaka',
            'zip_code'                   => '1207',
            'landmark'                   => 'Near City Center',
            'timezone'                   => 'Asia/Dhaka',
            'tax_1_name'                 => 'VAT',
            'tax_1_no'                   => 'BIN-123456',
            'tax_2_name'                 => '',
            'tax_2_no'                   => '',
            'financial_year_start_month' => '7',
            'stock_accounting_method'    => 'fifo',
        ], $overrides);
    }

    /** The logo travels as a file, so this is a multipart post rather than JSON. */
    private function saveWithLogo(UploadedFile $logo, array $overrides = [])
    {
        return $this->post('/api/v1/business-settings', $this->payload($overrides) + ['logo' => $logo], ['Accept' => 'application/json']);
    }

    private function logoPathOf(User $user): ?string
    {
        return BusinessSetting::where('user_id', $user->id)->value('logo');
    }

    /* ---------------------------------------------------------- auth */

    public function test_business_settings_require_a_token(): void
    {
        $this->getJson('/api/v1/business-settings')->assertUnauthorized();
        $this->postJson('/api/v1/business-settings', $this->payload())->assertUnauthorized();

        $this->assertDatabaseCount('business_settings', 0);
    }

    /* ---------------------------------------------------------- show */

    public function test_show_is_null_before_anything_is_saved(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/business-settings')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_show_returns_the_saved_settings(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/business-settings', $this->payload())->assertOk();

        $this->getJson('/api/v1/business-settings')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'business_name', 'start_date', 'currency', 'language', 'logo_url', 'website',
                'business_contact_number', 'alternate_contact_number', 'country', 'state', 'city',
                'zip_code', 'landmark', 'timezone', 'tax_1_name', 'tax_1_no', 'tax_2_name', 'tax_2_no',
                'financial_year_start_month', 'stock_accounting_method',
            ]])
            ->assertJsonPath('data.business_name', 'Global Traders Inc.')
            ->assertJsonPath('data.start_date', '2024-03-15')
            ->assertJsonPath('data.financial_year_start_month', 7)
            ->assertJsonPath('data.logo_url', null)
            ->assertJsonMissingPath('data.user_id');
    }

    public function test_settings_belong_to_one_account_only(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/business-settings', $this->payload(['business_name' => 'Owner Co']))->assertOk();

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson('/api/v1/business-settings')->assertExactJson(['data' => null]);

        $this->postJson('/api/v1/business-settings', $this->payload(['business_name' => 'Other Co']))->assertOk();

        $this->assertDatabaseCount('business_settings', 2);
        $this->assertSame('Owner Co', BusinessSetting::where('user_id', $owner->id)->value('business_name'));
    }

    public function test_a_user_id_in_the_request_cannot_redirect_the_write(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        Sanctum::actingAs($attacker);

        $this->postJson('/api/v1/business-settings', $this->payload(['user_id' => $victim->id]))->assertOk();

        $this->assertDatabaseMissing('business_settings', ['user_id' => $victim->id]);
        $this->assertDatabaseHas('business_settings', ['user_id' => $attacker->id]);
    }

    /* ---------------------------------------------------------- save */

    public function test_first_save_creates_the_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/business-settings', $this->payload())
            ->assertOk()
            ->assertJsonPath('message', 'Business settings saved successfully.')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.timezone', 'Asia/Dhaka');

        $this->assertDatabaseHas('business_settings', [
            'user_id'                    => $user->id,
            'business_name'              => 'Global Traders Inc.',
            'currency'                   => 'USD',
            'financial_year_start_month' => 7,
            'stock_accounting_method'    => 'fifo',
        ]);
    }

    public function test_saving_again_overwrites_instead_of_adding_a_second_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/business-settings', $this->payload())->assertOk();

        $this->postJson('/api/v1/business-settings', $this->payload(['business_name' => 'Renamed Ltd', 'city' => 'Chattogram']))
            ->assertOk()
            ->assertJsonPath('data.business_name', 'Renamed Ltd');

        $this->assertDatabaseCount('business_settings', 1);
        $this->assertDatabaseHas('business_settings', ['user_id' => $user->id, 'business_name' => 'Renamed Ltd', 'city' => 'Chattogram']);
    }

    public function test_only_the_required_fields_are_needed(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $minimal = array_intersect_key($this->payload(), array_flip(self::REQUIRED_FIELDS));

        $this->postJson('/api/v1/business-settings', $minimal)
            ->assertOk()
            ->assertJsonPath('data.start_date', null)
            ->assertJsonPath('data.website', null)
            ->assertJsonPath('data.tax_1_name', null);
    }

    public function test_optional_fields_can_be_cleared(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/business-settings', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.tax_1_name', 'VAT');

        $this->postJson('/api/v1/business-settings', $this->payload([
            'start_date'              => '',
            'website'                 => '',
            'business_contact_number' => '',
            'tax_1_name'              => '',
            'tax_1_no'                => '',
        ]))
            ->assertOk()
            ->assertJsonPath('data.start_date', null)
            ->assertJsonPath('data.website', null)
            ->assertJsonPath('data.business_contact_number', null)
            ->assertJsonPath('data.tax_1_name', null);
    }

    public function test_every_required_field_is_enforced(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/business-settings', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(self::REQUIRED_FIELDS);

        $this->assertDatabaseCount('business_settings', 0);
    }

    public function test_a_failed_save_leaves_the_existing_row_untouched(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/business-settings', $this->payload())->assertOk();
        $this->postJson('/api/v1/business-settings', $this->payload(['business_name' => '', 'city' => 'Chattogram']))->assertUnprocessable();

        $this->assertDatabaseHas('business_settings', ['user_id' => $user->id, 'business_name' => 'Global Traders Inc.', 'city' => 'Dhaka']);
    }

    public function test_text_fields_respect_their_column_lengths(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/business-settings', $this->payload([
            'business_name' => str_repeat('a', 256),
            'zip_code'      => str_repeat('1', 21),
            'tax_1_name'    => str_repeat('a', 51),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['business_name', 'zip_code', 'tax_1_name']);
    }

    /* ---------------------------------------------------------- currency + language */

    public function test_currency_must_be_an_active_one(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // EUR exists but is switched off; XYZ does not exist at all.
        foreach (['EUR', 'XYZ'] as $code) {
            $this->postJson('/api/v1/business-settings', $this->payload(['currency' => $code]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['currency' => 'available currencies']);
        }

        $this->assertDatabaseCount('business_settings', 0);
    }

    public function test_language_must_be_an_active_one(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (['fr', 'xx'] as $code) {
            $this->postJson('/api/v1/business-settings', $this->payload(['language' => $code]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['language' => 'available languages']);
        }

        $this->assertDatabaseCount('business_settings', 0);
    }

    public function test_switching_a_currency_off_blocks_it_from_the_next_save(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/business-settings', $this->payload())->assertOk();

        Currency::where('code', 'USD')->update(['is_active' => false]);

        $this->postJson('/api/v1/business-settings', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency');
    }

    public function test_currency_code_is_stored_upper_case(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/business-settings', $this->payload(['currency' => 'usd']))
            ->assertOk()
            ->assertJsonPath('data.currency', 'USD');

        $this->assertSame('USD', BusinessSetting::where('user_id', $user->id)->value('currency'));
    }

    /* ---------------------------------------------------------- the other constrained fields */

    public function test_timezone_must_be_a_real_one(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (['Mars/Olympus_Mons', 'Dhaka', 'UTC+6'] as $zone) {
            $this->postJson('/api/v1/business-settings', $this->payload(['timezone' => $zone]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('timezone');
        }
    }

    public function test_the_older_zone_names_the_browser_still_offers_are_accepted(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // Intl.supportedValuesOf('timeZone') lists these; PHP's default
        // timezone group rejects every one of them.
        foreach (['Asia/Dhaka', 'Asia/Calcutta', 'Asia/Katmandu', 'Asia/Rangoon', 'Asia/Saigon', 'America/Buenos_Aires', 'Europe/London'] as $zone) {
            $this->postJson('/api/v1/business-settings', $this->payload(['timezone' => $zone]))
                ->assertOk()
                ->assertJsonPath('data.timezone', $zone);
        }
    }

    public function test_financial_year_start_month_must_be_a_month_number(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (['0', '13', 'abc', '1.5'] as $month) {
            $this->postJson('/api/v1/business-settings', $this->payload(['financial_year_start_month' => $month]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('financial_year_start_month');
        }

        foreach (['1', '12'] as $month) {
            $this->postJson('/api/v1/business-settings', $this->payload(['financial_year_start_month' => $month]))->assertOk();
        }
    }

    public function test_stock_accounting_method_must_be_a_known_one(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/business-settings', $this->payload(['stock_accounting_method' => 'average']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stock_accounting_method');

        foreach (BusinessSetting::STOCK_ACCOUNTING_METHODS as $method) {
            $this->postJson('/api/v1/business-settings', $this->payload(['stock_accounting_method' => $method]))
                ->assertOk()
                ->assertJsonPath('data.stock_accounting_method', $method);
        }
    }

    public function test_website_must_be_an_http_or_https_url(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (['not a url', 'ftp://files.example', 'javascript:alert(1)'] as $site) {
            $this->postJson('/api/v1/business-settings', $this->payload(['website' => $site]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('website');
        }
    }

    public function test_start_date_must_be_a_year_month_day_date(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (['15/03/2024', '2024-13-45', 'yesterday'] as $date) {
            $this->postJson('/api/v1/business-settings', $this->payload(['start_date' => $date]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('start_date');
        }
    }

    /* ---------------------------------------------------------- logo */

    public function test_logo_upload_is_stored_and_exposed_as_a_url(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->saveWithLogo(UploadedFile::fake()->image('logo.png', 200, 200))->assertOk();

        $path = $this->logoPathOf($user);

        $this->assertStringStartsWith('business-logos/', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith('/storage/'.$path, $response->json('data.logo_url'));
    }

    public function test_replacing_the_logo_removes_the_old_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->saveWithLogo(UploadedFile::fake()->image('first.png'))->assertOk();
        $first = $this->logoPathOf($user);

        $this->saveWithLogo(UploadedFile::fake()->image('second.jpg'))->assertOk();
        $second = $this->logoPathOf($user);

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_saving_without_a_new_logo_keeps_the_current_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->saveWithLogo(UploadedFile::fake()->image('logo.png'))->assertOk();
        $path = $this->logoPathOf($user);

        $this->postJson('/api/v1/business-settings', $this->payload(['business_name' => 'Renamed Ltd']))
            ->assertOk()
            ->assertJsonPath('data.business_name', 'Renamed Ltd')
            ->assertJsonPath('data.logo_url', fn ($url) => str_ends_with($url, '/storage/'.$path));

        $this->assertSame($path, $this->logoPathOf($user));
        Storage::disk('public')->assertExists($path);
    }

    public function test_logo_must_be_a_small_raster_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $rejected = [
            'a pdf'      => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
            // An SVG can carry script, and this one would be served from our own domain.
            'an svg'     => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'too large'  => UploadedFile::fake()->image('big.png')->size(3000),
        ];

        foreach ($rejected as $what => $file) {
            $response = $this->saveWithLogo($file);

            $this->assertSame(422, $response->getStatusCode(), "{$what} should have been rejected");
            $response->assertJsonValidationErrors('logo');
        }

        $this->assertDatabaseCount('business_settings', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /* ---------------------------------------------------------- activity log */

    public function test_each_save_is_logged_once_with_the_changed_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/business-settings', $this->payload())->assertOk();
        $this->postJson('/api/v1/business-settings', $this->payload(['city' => 'Chattogram']))->assertOk();

        // One descriptive entry per save, and no generic `api_auto` one on top.
        $logs = ActivityLog::where('user_id', $user->id)->orderBy('id')->get();

        $this->assertCount(2, $logs);
        $this->assertSame('created', $logs[0]->action);
        $this->assertSame('Created their business settings', $logs[0]->description);
        $this->assertEqualsCanonicalizing(array_keys($this->payload()), $logs[0]->properties['fields']);
        $this->assertSame('updated', $logs[1]->action);
        $this->assertSame('Updated their business settings', $logs[1]->description);
        $this->assertSame(['city'], $logs[1]->properties['fields']);
    }
}
