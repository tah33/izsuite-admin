<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase B2b: counting a POS session's cash by notes and coins
 * (section 18.27) - switched on in Settings → Point of Sale with each
 * currency's notes and coins, izSuite's usual ones to start; the cash
 * counted is the total of how many of each, and the Z report keeps the count.
 */
class PosCashCountTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const SETTINGS = '/api/v1/business-settings/pos';

    private const SESSIONS = '/api/v1/pos/sessions';

    private Workspace $business;

    private int $register;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $pos = Application::create(['app_key' => 'pos', 'name' => 'Point of Sale', 'family_key' => 'pos', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 30, 'is_active' => true, 'status' => 'available']);
        $pos->packages()->attach(Package::pluck('id'));

        $user           = $this->signIn();
        $this->business = $this->businessOf($user);
        $this->business->forceFill(['base_currency' => 'USD', 'additional_currencies' => ['EUR', 'XAF']])->save();
        $dhaka = $this->business->locations()->create(['user_id' => $user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $cash           = (int) $this->getJson('/api/v1/pos/registers/options')->json('data.payment_methods.0.id');
        $this->register = (int) $this->postJson('/api/v1/pos/registers', [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$cash], 'cashier_ids' => [$user->id],
        ])->assertCreated()->json('data.id');
    }

    private function counting(array $fields): TestResponse
    {
        return $this->putJson(self::SETTINGS.'/cash-count', ['variance_limit' => null, 'counts_denominations' => true, ...$fields]);
    }

    /** A session opened on Front Counter with $100: its id. */
    private function opened(): int
    {
        return $this->postJson(self::SESSIONS, ['pos_register_id' => $this->register, 'opening_float' => '100'])->assertCreated()->json('data.id');
    }

    public function test_each_currency_starts_with_its_usual_notes_and_coins(): void
    {
        $settings = $this->getJson(self::SETTINGS)->assertOk()->assertJsonPath('data.counts_denominations', false)->json('data.denominations');
        $this->assertSame(['USD', 'EUR', 'XAF'], array_column($settings, 'currency'));
        $this->assertSame(['100.00', '50.00', '20.00', '10.00', '5.00', '2.00', '1.00', '0.50', '0.25', '0.10', '0.05', '0.01'], $settings[0]['values']);
        $this->assertSame('200.00', $settings[1]['values'][0]);
        $this->assertSame([], $settings[2]['values']);

        // Each saved to the cent, once, the largest first; the others as they were.
        $saved = $this->counting(['variance_limit' => '5', 'denominations' => [
            ['currency' => 'USD', 'values' => ['1', '20', '5', '20', '0.5']],
            ['currency' => 'XAF', 'values' => ['500', '1000']],
        ]])->assertOk()
            ->assertJsonPath('message', 'Closing settings saved.')
            ->assertJsonPath('data.variance_limit', '5.00')
            ->assertJsonPath('data.counts_denominations', true)
            ->json('data.denominations');
        $this->assertSame(['20.00', '5.00', '1.00', '0.50'], $saved[0]['values']);
        $this->assertSame('200.00', $saved[1]['values'][0]);
        $this->assertSame(['1000.00', '500.00'], $saved[2]['values']);
        $this->assertTrue(ActivityLog::query()->where('description', 'Updated the POS cash count settings')->exists());

        $this->counting(['denominations' => [['currency' => 'GBP', 'values' => ['10']]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['denominations.0.currency' => "Choose one of the business's currencies."]);
        $this->counting(['denominations' => [['currency' => 'USD', 'values' => ['0']]]])->assertUnprocessable()->assertJsonValidationErrors('denominations.0.values.0');

        // The other settings no longer touch them.
        $this->putJson(self::SETTINGS, ['receipt_footer' => 'Thank you'])->assertOk()->assertJsonPath('data.variance_limit', '5.00')->assertJsonPath('data.counts_denominations', true);
    }

    public function test_a_session_is_counted_by_its_notes_and_coins(): void
    {
        $this->counting(['denominations' => [['currency' => 'USD', 'values' => ['20', '5', '1', '0.50']]]])->assertOk();

        $session = $this->opened();
        $this->getJson(self::SESSIONS."/{$session}")->assertOk()->assertJsonPath('data.denominations', ['20.00', '5.00', '1.00', '0.50']);

        $this->postJson(self::SESSIONS."/{$session}/close", ['counted_cash' => '100'])->assertUnprocessable()
            ->assertJsonValidationErrors(['denominations' => 'Count the notes and coins: how many of each.']);
        $this->postJson(self::SESSIONS."/{$session}/close", ['denominations' => [['value' => '2', 'count' => 1]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['denominations' => 'USD 2.00 is not one of the notes and coins Settings → Point of Sale counts by.']);
        $this->postJson(self::SESSIONS."/{$session}/close", ['denominations' => [['value' => '20', 'count' => 1.5]]])->assertUnprocessable()->assertJsonValidationErrors('denominations.0.count');

        // How many of each, the same one twice added up: $100.50 counted.
        $this->postJson(self::SESSIONS."/{$session}/close", ['variance_reason' => 'A coin from the last shift', 'denominations' => [
            ['value' => '20', 'count' => 4], ['value' => '5', 'count' => 3], ['value' => '1', 'count' => 4], ['value' => '0.5', 'count' => 1], ['value' => '1', 'count' => 1], ['value' => '0.50', 'count' => 0],
        ]])->assertOk()
            ->assertJsonPath('data.z_report.counted_cash', '100.50')
            ->assertJsonPath('data.z_report.variance', '0.50')
            ->assertJsonPath('data.z_report.denominations', [
                ['value' => '20.00', 'count' => 4, 'amount' => '80.00'],
                ['value' => '5.00', 'count' => 3, 'amount' => '15.00'],
                ['value' => '1.00', 'count' => 5, 'amount' => '5.00'],
                ['value' => '0.50', 'count' => 1, 'amount' => '0.50'],
            ])
            ->assertJsonPath('data.denominations', null);
    }

    public function test_cash_is_counted_as_one_amount_when_not_by_notes_and_coins(): void
    {
        // Switched off: one amount.
        $session = $this->opened();
        $this->getJson(self::SESSIONS."/{$session}")->assertJsonPath('data.denominations', null);
        $this->postJson(self::SESSIONS."/{$session}/close", ['counted_cash' => '100', 'denominations' => [['value' => '20', 'count' => 9]]])->assertOk()
            ->assertJsonPath('data.z_report.counted_cash', '100.00')
            ->assertJsonPath('data.z_report.denominations', null);

        // On, but the currency has none: one amount too.
        $this->counting(['denominations' => [['currency' => 'USD', 'values' => []]]])->assertOk();
        $session = $this->opened();
        $this->getJson(self::SESSIONS."/{$session}")->assertJsonPath('data.denominations', null);
        $this->postJson(self::SESSIONS."/{$session}/close", [])->assertUnprocessable()->assertJsonValidationErrors(['counted_cash' => 'Enter the cash counted in the drawer.']);
        $this->postJson(self::SESSIONS."/{$session}/close", ['counted_cash' => '100'])->assertOk()->assertJsonPath('data.z_report.denominations', null);
    }
}
