<?php

namespace Tests\Feature;

use App\Jobs\CreateStarterWorkspace;
use App\Models\Admin\Currency;
use App\Models\Admin\Language;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * The First-Time Business Setup wizard (Doc 11 section 3) - Doc 11 Phase C:
 * a new business starts at Business Information, takes the steps in order
 * once its information is saved - only the payment account may be skipped -
 * and is set up when it finishes from Review. The sidebar says whether it is.
 * Businesses made before the wizard count as set up.
 */
class SetupWizardTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        Language::create(['name' => 'English', 'code' => 'en', 'native_name' => 'English', 'is_default' => true, 'is_active' => true]);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);
        Currency::firstOrCreate(['code' => 'BDT'], ['name' => 'Bangladeshi Taka', 'symbol' => '৳', 'is_default' => false, 'is_active' => true]);

        $this->user = $this->signIn();
    }

    private function profile(array $overrides = []): array
    {
        return [
            'business_name'           => 'Uddin Traders Ltd',
            'language'                => 'en',
            'country'                 => 'BD',
            'timezone'                => 'Asia/Dhaka',
            'stock_accounting_method' => 'fifo',
            ...$overrides,
        ];
    }

    private function reach(string $step, array $headers = []): TestResponse
    {
        return $this->putJson('/api/v1/setup', ['step' => $step], $headers);
    }

    public function test_a_new_business_starts_its_setup_at_business_information(): void
    {
        $this->getJson('/api/v1/setup')->assertOk()->assertExactJson(['data' => ['step' => 'business', 'completed' => false, 'currency' => null]]);

        $this->assertFalse($this->getJson('/api/v1/navigation')->json('data.workspace.setup_completed'));
    }

    public function test_the_steps_are_taken_in_order_once_the_business_information_is_saved(): void
    {
        $this->reach('regional')->assertJsonValidationErrors(['step' => 'Save your business information first.']);

        $this->postJson('/api/v1/business-settings', $this->profile())->assertOk();

        // One step at a time, never ahead.
        $this->reach('currency')->assertJsonValidationErrors(['step' => 'Finish the steps before this one first.']);
        $this->reach('regional')->assertOk()->assertJsonPath('message', 'Setup progress saved.')->assertJsonPath('data.step', 'regional');
        $this->reach('currency')->assertOk()->assertJsonPath('data.step', 'currency');

        // Back to change a step: the setup stays where it got to.
        $this->reach('business')->assertOk()->assertJsonPath('data.step', 'currency');
        $this->getJson('/api/v1/setup')->assertJsonPath('data.step', 'currency');

        $this->reach('nowhere')->assertJsonValidationErrors(['step' => 'Choose the setup step to move on to.']);

        $this->assertSame(
            ['Reached "Country and Regional Settings" in the business setup', 'Reached "Base Currency" in the business setup'],
            ActivityLog::where('user_id', $this->user->id)->where('description', 'like', 'Reached%')->orderBy('id')->pluck('description')->all(),
        );
    }

    public function test_the_base_currency_suggested_is_the_one_the_country_pays_in(): void
    {
        $this->postJson('/api/v1/business-settings', $this->profile())->assertOk();
        $this->getJson('/api/v1/setup')->assertJsonPath('data.currency', 'BDT');

        // Not one the platform offers: nothing is suggested.
        $this->postJson('/api/v1/business-settings', $this->profile(['country' => 'JP']))->assertOk();
        $this->getJson('/api/v1/setup')->assertJsonPath('data.currency', null);
    }

    public function test_the_payment_account_may_be_skipped_and_the_setup_finishes_from_review(): void
    {
        $this->postJson('/api/v1/business-settings', $this->profile())->assertOk();

        foreach (['regional', 'currency', 'taxes', 'location', 'documents', 'payment_account'] as $step) {
            $this->reach($step)->assertOk();
        }

        $this->postJson('/api/v1/setup/complete')->assertJsonValidationErrors(['step' => 'Finish the steps before this one first.']);

        // On to Review with no payment account: it can be added later.
        $this->reach('review')->assertOk();
        $this->postJson('/api/v1/setup/complete')
            ->assertOk()
            ->assertJsonPath('message', 'Your business is set up.')
            ->assertJsonPath('data.completed', true);

        $this->assertTrue($this->getJson('/api/v1/navigation')->json('data.workspace.setup_completed'));
        $this->assertSame('Finished the business setup', ActivityLog::where('user_id', $this->user->id)->orderByDesc('id')->value('description'));

        // Set up, it stays set up.
        $this->reach('business')->assertOk()->assertJsonPath('data.completed', true);
        $this->postJson('/api/v1/setup/complete')->assertOk()->assertJsonPath('data.step', 'review');
    }

    public function test_a_business_named_after_its_plan_at_registration_takes_its_own_name(): void
    {
        $business = $this->businessOf($this->user);
        $business->forceFill(['name' => CreateStarterWorkspace::NAME])->save();

        $this->postJson('/api/v1/business-settings', $this->profile(['trading_name' => 'Uddin Traders']))->assertOk();
        $this->reach('regional')->assertOk();

        $this->assertSame('Uddin Traders', $business->fresh()->name);
        $this->assertSame('Uddin Traders', $this->getJson('/api/v1/navigation')->json('data.workspace.name'));

        // One its owner named keeps that name.
        $other   = $this->user->workspaces()->create(['name' => 'Gulshan Shop']);
        $picking = ['X-Workspace-Id' => (string) $other->id];

        $this->postJson('/api/v1/business-settings', $this->profile(['business_name' => 'Gulshan Retail Ltd']), $picking)->assertOk();
        $this->reach('regional', $picking)->assertOk();

        $this->assertSame('Gulshan Shop', $other->fresh()->name);
    }

    public function test_businesses_made_before_the_wizard_count_as_set_up(): void
    {
        $migration = require database_path('migrations/2025_01_01_000100_add_setup_progress_to_workspaces.php');
        $migration->down();

        $old = $this->user->workspaces()->create(['name' => 'Old Business']);

        $migration->up();

        $this->assertTrue($old->fresh()->isSetUp());
        $this->assertTrue($this->businessOf($this->user)->fresh()->isSetUp());
    }
}
