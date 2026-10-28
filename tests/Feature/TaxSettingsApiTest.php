<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\TaxRate;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Settings > Taxes > Tax Options (Doc 11 sections 9 and 11): whether prices
 * include tax, how tax is rounded, and the default sales and purchase tax.
 * The tax choice on its own is BusinessSetupApiTest's.
 */
class TaxSettingsApiTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        $this->user = $this->signIn();
    }

    private function rate(string $code, array $sides, bool $active = true): TaxRate
    {
        $rate = $this->businessOf($this->user)->taxRates()->create([
            'name' => $code, 'code' => $code, 'rate' => '15', 'treatment' => 'standard', 'effective_from' => '2026-01-01', 'is_active' => $active,
        ]);

        $rate->forceFill(collect($sides)->mapWithKeys(fn (string $side) => ["for_{$side}" => true])->all())->save();

        return $rate;
    }

    public function test_the_options_and_defaults_are_saved_and_logged(): void
    {
        $vat   = $this->rate('VAT', ['sales', 'purchases']);
        $input = $this->rate('INPUT', ['purchases']);

        $this->putJson('/api/v1/business-settings/taxes', [
            'tax_mode'                => 'enabled',
            'price_tax_mode'          => 'inclusive',
            'tax_rounding'            => 'total',
            'default_sales_tax_id'    => $vat->id,
            'default_purchase_tax_id' => $input->id,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Tax settings saved successfully.');

        $this->getJson('/api/v1/business-settings/taxes')->assertExactJson(['data' => [
            'tax_mode'                => 'enabled',
            'price_tax_mode'          => 'inclusive',
            'tax_rounding'            => 'total',
            'default_sales_tax_id'    => $vat->id,
            'default_purchase_tax_id' => $input->id,
        ]]);

        $log = ActivityLog::where('user_id', $this->user->id)->sole();
        $this->assertSame('Updated the tax settings', $log->description);
        $this->assertSame(['tax_mode', 'price_tax_mode', 'tax_rounding', 'default_sales_tax_id', 'default_purchase_tax_id'], $log->properties['fields']);
    }

    public function test_a_new_default_takes_over_and_none_clears_it(): void
    {
        $first  = $this->rate('A', ['sales']);
        $second = $this->rate('B', ['sales']);

        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'enabled', 'default_sales_tax_id' => $first->id])->assertOk();
        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'enabled', 'default_sales_tax_id' => $second->id])->assertOk();

        $this->assertSame([$second->id], TaxRate::where('default_for_sales', true)->pluck('id')->all());

        // Left out, the defaults stay as they are.
        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'disabled'])->assertJsonPath('data.default_sales_tax_id', $second->id);

        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'enabled', 'default_sales_tax_id' => null])->assertJsonPath('data.default_sales_tax_id', null);
        $this->assertSame(0, TaxRate::where('default_for_sales', true)->count());
    }

    public function test_a_default_is_an_active_rate_of_the_business_for_its_side(): void
    {
        $purchaseOnly = $this->rate('IN', ['purchases']);
        $inactive     = $this->rate('OLD', ['sales'], false);
        $elsewhere    = $this->user->workspaces()->create(['name' => 'Second Business'])->taxRates()->create([
            'name' => 'VAT', 'code' => 'VAT', 'rate' => '15', 'treatment' => 'standard', 'effective_from' => '2026-01-01', 'for_sales' => true,
        ]);

        foreach ([$purchaseOnly, $inactive, $elsewhere] as $rate) {
            $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'enabled', 'default_sales_tax_id' => $rate->id])
                ->assertJsonValidationErrors(['default_sales_tax_id' => 'Choose one of your active sales taxes.']);
        }

        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'enabled', 'price_tax_mode' => 'gross', 'tax_rounding' => 'never'])
            ->assertJsonValidationErrors(['price_tax_mode', 'tax_rounding']);
    }
}
