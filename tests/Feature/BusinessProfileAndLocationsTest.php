<?php

namespace Tests\Feature;

use App\Models\Admin\Language;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Services\Documents\DocumentRenderer;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * The business profile and its locations (Doc 11 sections 4 and 6, Doc 12
 * sections 17 and 19 - Doc 11 Phase A): what the profile holds and the
 * documents it numbers print, the business ID, a new business's Main
 * Location, inactive locations and new transactions, and the migration that
 * brought the old rows along.
 */
class BusinessProfileAndLocationsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const MIGRATION = 'migrations/2025_01_01_000098_add_business_profile_and_location_details.php';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        Language::create(['name' => 'English', 'code' => 'en', 'native_name' => 'English', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();
    }

    public function test_the_profile_prints_on_the_documents_it_numbers(): void
    {
        $this->postJson('/api/v1/business-settings', $this->profile())
            ->assertOk()
            ->assertJsonPath('data.trading_name', 'Global Traders')
            ->assertJsonPath('data.legal_structure', 'private_limited')
            ->assertJsonPath('data.country', 'BD');

        $id       = $this->issuedInvoice();
        $snapshot = SalesDocument::findOrFail($id)->business_snapshot;

        $this->assertSame(
            ['Global Traders Inc.', 'Global Traders', 'C-123456', 'accounts@globaltraders.example'],
            [$snapshot['name'], $snapshot['trading_name'], $snapshot['registration_number'], $snapshot['email']],
        );
        $this->assertSame(['House 12, Road 5', 'Near City Center', 'Dhaka', 'Dhaka', '1207', 'Bangladesh'], $snapshot['address']);

        $html = $this->html($id);
        $this->assertStringContainsString('Trading as Global Traders', $html);
        $this->assertStringContainsString('Registration No.: C-123456', $html);
        $this->assertStringContainsString('accounts@globaltraders.example', $html);

        // Changed later, the profile never reaches a document numbered before.
        $this->postJson('/api/v1/business-settings', $this->profile(['trading_name' => 'GT Wholesale']))->assertOk();
        $this->assertSame('Global Traders', SalesDocument::findOrFail($id)->business_snapshot['trading_name']);
    }

    public function test_a_country_is_a_code_and_the_address_may_be_left_empty(): void
    {
        $this->postJson('/api/v1/business-settings', $this->profile(['country' => 'Bangladesh']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.country.0', 'Choose the country from the list.');
        $this->postJson('/api/v1/business-settings', $this->profile(['legal_structure' => 'empire', 'email' => 'not an e-mail']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['legal_structure', 'email']);

        $this->postJson('/api/v1/business-settings', $this->profile(['address_line_1' => '', 'address_line_2' => '', 'city' => '', 'state' => '', 'zip_code' => '']))
            ->assertOk()
            ->assertJsonPath('data.address_line_1', null)
            ->assertJsonPath('data.city', null);
    }

    public function test_the_business_id_rides_in_the_sidebar(): void
    {
        $business = $this->businessOf($this->user);

        $this->getJson('/api/v1/navigation')->assertOk()->assertJsonPath('data.workspace.business_id', sprintf('BW-%06d', $business->id));
    }

    public function test_a_new_business_starts_with_its_main_location_as_its_primary(): void
    {
        $this->user->forceFill(['timezone' => 'Asia/Dhaka'])->save();
        $business = $this->user->workspaces()->create(['name' => 'Second Business']);

        app(SetUpNewWorkspace::class)->handle($business, $this->user, 'api');

        $location = $business->locations()->sole();
        $this->assertSame(['Main Location', 'MAIN', true, true, 'Asia/Dhaka'], [$location->name, $location->code, $location->is_primary, $location->is_active, $location->timezone]);
    }

    public function test_new_transactions_take_only_active_locations(): void
    {
        $business = $this->businessOf($this->user);
        $main     = $business->locations()->create(['user_id' => $this->user->id, 'name' => 'Main Location', 'code' => 'MAIN', 'is_primary' => true]);
        $depot    = $business->locations()->create(['user_id' => $this->user->id, 'name' => 'Port Depot', 'code' => 'PORT']);
        $draft    = $this->draft('invoices', ['location_id' => $depot->id]);

        $this->postJson("/api/v1/locations/{$depot->id}/deactivate")->assertOk();

        // Something new goes to an active location...
        $this->postJson('/api/v1/sales/invoices', $this->invoice(['location_id' => $depot->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.location_id.0', 'Port Depot is inactive. Choose an active location.');
        $this->postJson('/api/v1/sales/invoices', $this->invoice(['location_id' => $main->id]))->assertCreated();

        // ...while a draft keeps the one it has.
        $this->putJson("/api/v1/sales/invoices/{$draft}", $this->invoice(['location_id' => $depot->id, 'notes' => 'Still at the depot']))->assertOk();
    }

    public function test_the_migration_brings_the_old_profile_and_locations_along(): void
    {
        $owner    = User::factory()->create(['timezone' => 'Asia/Dhaka']);
        $business = $owner->workspaces()->create(['name' => 'Old Business']);
        $other    = $owner->workspaces()->create(['name' => 'No Settings']);

        // The fiscal year these rows are saved with moved out later.
        (require database_path('migrations/2025_01_01_000099_add_regional_settings_to_workspaces.php'))->down();

        $migration = require database_path(self::MIGRATION);
        $migration->down();

        $now = now();

        DB::table('business_settings')->insert([
            'user_id' => $owner->id, 'workspace_id' => $business->id, 'business_name' => 'Old Business', 'language' => 'en', 'country' => 'bangladesh',
            'state' => 'Dhaka', 'city' => 'Dhaka', 'zip_code' => '1207', 'landmark' => 'Near City Center', 'timezone' => 'Asia/Kolkata',
            'financial_year_start_month' => 7, 'stock_accounting_method' => 'fifo', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('locations')->insert([
            ['user_id' => $owner->id, 'workspace_id' => $business->id, 'name' => 'Main Location', 'country' => 'Bangladesh', 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => $owner->id, 'workspace_id' => $business->id, 'name' => 'Main Store', 'country' => 'Atlantis', 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => $owner->id, 'workspace_id' => $other->id, 'name' => 'Main Location', 'country' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $migration->up();

        // The Landmark is the first address line; a country it knows is a code, one it does not stays as typed.
        $settings = DB::table('business_settings')->where('workspace_id', $business->id)->first();
        $this->assertSame(['Near City Center', null, 'BD'], [$settings->address_line_1, $settings->address_line_2, $settings->country]);

        // Each business's oldest location is its Primary, codes come from names, time zones from the settings - else the owner.
        $this->assertSame(
            [['Main Location', 'MAIN', 1, 'BD', 'Asia/Kolkata'], ['Main Store', 'MAIN2', 0, 'Atlantis', 'Asia/Kolkata'], ['Main Location', 'MAIN', 1, null, 'Asia/Dhaka']],
            DB::table('locations')->orderBy('id')->get()->map(fn ($row) => [$row->name, $row->code, (int) $row->is_primary, $row->country, $row->timezone])->all(),
        );
    }

    /** A complete profile, as Settings > Business sends it. */
    private function profile(array $overrides = []): array
    {
        return [
            'business_name'              => 'Global Traders Inc.',
            'trading_name'               => 'Global Traders',
            'registration_number'        => 'C-123456',
            'legal_structure'            => 'private_limited',
            'industry'                   => 'Wholesale trade',
            'language'                   => 'en',
            'email'                      => 'accounts@globaltraders.example',
            'address_line_1'             => 'House 12, Road 5',
            'address_line_2'             => 'Near City Center',
            'city'                       => 'Dhaka',
            'state'                      => 'Dhaka',
            'zip_code'                   => '1207',
            'country'                    => 'BD',
            'timezone'                   => 'Asia/Dhaka',
            'stock_accounting_method'    => 'fifo',
            ...$overrides,
        ];
    }

    /** The document as its PDF and secure link draw it. */
    private function html(int $id): string
    {
        $document = SalesDocument::with(['lines', 'contact', 'location', 'source'])->findOrFail($id);

        return app(DocumentRenderer::class)->sales($this->businessOf($this->user), $document)->html;
    }
}
