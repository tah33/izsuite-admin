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
 * Settings > Taxes > Tax Rates (Doc 11 section 10): a business's rates, their
 * codes, treatments and dates, and what keeps its default taxes usable.
 */
class TaxRatesApiTest extends TestCase
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

    private function vat(array $fields = []): array
    {
        return [
            'name'           => 'VAT',
            'code'           => 'vat15',
            'rate'           => '15',
            'treatment'      => 'standard',
            'applies_to'     => ['sales', 'purchases'],
            'effective_from' => '2026-01-01',
            ...$fields,
        ];
    }

    private function add(array $fields = []): int
    {
        return $this->postJson('/api/v1/tax-rates', $this->vat($fields))->assertCreated()->json('data.id');
    }

    public function test_a_rate_is_added_with_its_code_upper_case_and_listed_active_first(): void
    {
        $data = $this->postJson('/api/v1/tax-rates', $this->vat(['effective_to' => '2026-12-31', 'description' => 'Standard VAT']))
            ->assertCreated()
            ->assertJsonPath('message', 'Tax rate added successfully.')
            ->json('data');

        $this->assertSame([
            'name'           => 'VAT',
            'code'           => 'VAT15',
            'rate'           => '15.0000',
            'treatment'      => 'standard',
            'applies_to'     => ['sales', 'purchases'],
            'recoverability' => null,
            'effective_from' => '2026-01-01',
            'effective_to'   => '2026-12-31',
            'description'    => 'Standard VAT',
            'is_active'      => true,
            'default_for'    => [],
        ], array_diff_key($data, ['id' => true]));

        $this->add(['name' => 'Archived', 'code' => 'OLD', 'is_active' => false]);
        $this->add(['name' => 'Exempt', 'code' => 'EX', 'treatment' => 'exempt', 'rate' => null]);

        $this->assertSame(['Exempt', 'VAT', 'Archived'], array_column($this->getJson('/api/v1/tax-rates')->assertOk()->json('data'), 'name'));
    }

    public function test_only_a_standard_rate_has_a_percentage(): void
    {
        $this->postJson('/api/v1/tax-rates', $this->vat(['code' => 'ZR', 'treatment' => 'zero_rated', 'rate' => '5']))
            ->assertCreated()
            ->assertJsonPath('data.rate', '0.0000');

        $this->postJson('/api/v1/tax-rates', $this->vat(['rate' => '0']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rate' => 'A standard rate is above 0%. For 0%, choose Zero-Rated, Exempt, Out of Scope or No Tax.']);

        $this->postJson('/api/v1/tax-rates', $this->vat(['rate' => '100.5']))->assertJsonValidationErrors(['rate' => 'A rate is at most 100%.']);
        $this->postJson('/api/v1/tax-rates', $this->vat(['rate' => '7.12345']))->assertJsonValidationErrors('rate');
        $this->postJson('/api/v1/tax-rates', $this->vat(['treatment' => 'reduced']))->assertJsonValidationErrors('treatment');

        $this->postJson('/api/v1/tax-rates', $this->vat(['rate' => '7.125']))->assertCreated()->assertJsonPath('data.rate', '7.1250');
    }

    public function test_a_code_is_used_once_in_a_business_and_dates_run_forwards(): void
    {
        $this->add();

        $this->postJson('/api/v1/tax-rates', $this->vat(['code' => 'Vat15']))
            ->assertJsonValidationErrors(['code' => 'Another tax rate already has this code.']);
        $this->postJson('/api/v1/tax-rates', $this->vat(['code' => 'VAT 15']))->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/tax-rates', $this->vat(['code' => 'V2', 'applies_to' => []]))->assertJsonValidationErrors(['applies_to' => 'Choose what this tax applies to.']);
        $this->postJson('/api/v1/tax-rates', $this->vat(['code' => 'V2', 'applies_to' => ['payroll']]))->assertJsonValidationErrors('applies_to.0');
        $this->postJson('/api/v1/tax-rates', $this->vat(['code' => 'V2', 'effective_to' => '2025-12-31']))
            ->assertJsonValidationErrors(['effective_to' => 'The end date cannot come before the start date.']);

        // Another business has codes of its own.
        $other = $this->user->workspaces()->create(['name' => 'Second Business']);
        $this->postJson('/api/v1/tax-rates', $this->vat(), $this->pick($other))->assertCreated();
    }

    public function test_recoverability_is_kept_only_for_purchase_or_expense_tax(): void
    {
        $this->postJson('/api/v1/tax-rates', $this->vat(['code' => 'OUT', 'applies_to' => ['sales'], 'recoverability' => 'recoverable']))
            ->assertJsonPath('data.recoverability', null);

        $this->postJson('/api/v1/tax-rates', $this->vat(['code' => 'IN', 'applies_to' => ['expenses'], 'recoverability' => 'non_recoverable']))
            ->assertJsonPath('data.recoverability', 'non_recoverable');
    }

    public function test_a_rate_is_edited_and_deleted_and_each_write_logged(): void
    {
        $id = $this->add();

        $this->putJson("/api/v1/tax-rates/{$id}", $this->vat(['name' => 'Value Added Tax', 'rate' => '16']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Value Added Tax')
            ->assertJsonPath('data.rate', '16.0000');

        $this->deleteJson("/api/v1/tax-rates/{$id}")->assertOk()->assertJsonPath('message', 'Tax rate deleted successfully.');

        $this->assertSame(0, TaxRate::count());

        $logs = ActivityLog::where('user_id', $this->user->id)->orderBy('id')->get();
        $this->assertSame(['Added tax rate "VAT"', 'Updated tax rate "Value Added Tax"', 'Deleted tax rate "Value Added Tax"'], $logs->pluck('description')->all());
        $this->assertSame(['name', 'rate'], $logs[1]->properties['fields']);
    }

    public function test_another_business_rate_answers_like_one_that_does_not_exist(): void
    {
        $other = $this->user->workspaces()->create(['name' => 'Second Business']);
        $id    = $this->postJson('/api/v1/tax-rates', $this->vat(), $this->pick($other))->json('data.id');

        $this->putJson("/api/v1/tax-rates/{$id}", $this->vat())->assertNotFound()->assertJsonPath('message', 'Tax rate not found.');
        $this->deleteJson("/api/v1/tax-rates/{$id}")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/tax-rates')->json('data'));
    }

    public function test_the_default_tax_stays_usable_until_another_is_chosen(): void
    {
        $id = $this->add();

        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'enabled', 'default_sales_tax_id' => $id])->assertOk();
        $this->getJson('/api/v1/tax-rates')->assertJsonPath('data.0.default_for', ['sales']);

        $this->putJson("/api/v1/tax-rates/{$id}", $this->vat(['is_active' => false]))
            ->assertConflict()
            ->assertJsonPath('message', '"VAT" is your default sales tax, so it cannot be switched off. Choose another default - or none - under Tax Options first.');

        $this->putJson("/api/v1/tax-rates/{$id}", $this->vat(['applies_to' => ['purchases']]))
            ->assertJsonValidationErrors(['applies_to' => 'This is your default sales tax, so it must apply to sales. Choose another default under Tax Options first.']);

        $this->deleteJson("/api/v1/tax-rates/{$id}")->assertConflict();

        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'enabled', 'default_sales_tax_id' => null])->assertOk();

        $this->putJson("/api/v1/tax-rates/{$id}", $this->vat(['is_active' => false]))->assertOk()->assertJsonPath('data.is_active', false);
        $this->deleteJson("/api/v1/tax-rates/{$id}")->assertOk();
    }

    public function test_a_rate_items_start_with_is_switched_off_rather_than_deleted(): void
    {
        $id = $this->add();
        $this->businessOf($this->user)->items()->create(['type' => 'physical', 'usage' => 'both', 'unit' => 'Piece', 'name' => 'Office Chair', 'sales_tax_id' => $id]);

        $this->deleteJson("/api/v1/tax-rates/{$id}")
            ->assertConflict()
            ->assertJsonPath('message', '1 item starts with this tax. Deactivate it instead, or change those items first.');

        $this->putJson("/api/v1/tax-rates/{$id}", $this->vat(['is_active' => false]))->assertOk();
        $this->assertSame(1, TaxRate::count());
    }
}
