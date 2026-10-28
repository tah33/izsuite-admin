<?php

namespace Tests\Feature;

use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Settings > Taxes > Withholding Tax (Doc 11 sections 9 and 12): switching it
 * on, and the rates - added, listed, changed and deleted, each the business's
 * own. A rate withheld on a payment is kept (see SalesPaymentsApiTest).
 */
class WithholdingTaxesApiTest extends TestCase
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

    private function rate(array $fields = []): array
    {
        return [
            'name'           => 'WHT on Services',
            'code'           => 'wht10',
            'rate'           => '10',
            'applies_to'     => ['customer_payments'],
            'effective_from' => '2026-01-01',
            ...$fields,
        ];
    }

    public function test_rates_are_added_listed_changed_and_deleted(): void
    {
        $data = $this->postJson('/api/v1/withholding-taxes', $this->rate())
            ->assertCreated()
            ->assertJsonPath('message', 'Withholding tax added successfully.')
            ->json('data');

        $this->assertSame(['id', 'name', 'code', 'rate', 'applies_to', 'effective_from', 'effective_to', 'is_active'], array_keys($data));
        $this->assertSame(['WHT10', '10.0000', ['customer_payments'], true], [$data['code'], $data['rate'], $data['applies_to'], $data['is_active']]);

        $this->postJson('/api/v1/withholding-taxes', $this->rate(['code' => 'WHT10']))->assertJsonValidationErrors(['code' => 'Another withholding tax already has this code.']);
        $this->postJson('/api/v1/withholding-taxes', $this->rate(['code' => 'WHT0', 'rate' => '0', 'applies_to' => []]))->assertJsonValidationErrors(['rate', 'applies_to']);

        $this->putJson("/api/v1/withholding-taxes/{$data['id']}", $this->rate(['rate' => '7.5', 'applies_to' => ['customer_payments', 'supplier_payments']]))
            ->assertOk()
            ->assertJsonPath('data.rate', '7.5000')
            ->assertJsonPath('data.applies_to', ['customer_payments', 'supplier_payments']);

        $this->getJson('/api/v1/withholding-taxes')->assertOk()->assertJsonPath('data.*.code', ['WHT10']);

        $this->deleteJson("/api/v1/withholding-taxes/{$data['id']}")->assertOk()->assertJsonPath('message', 'Withholding tax deleted successfully.');
        $this->getJson('/api/v1/withholding-taxes')->assertJsonPath('data', []);
    }

    public function test_another_business_rate_is_not_found(): void
    {
        $id     = $this->postJson('/api/v1/withholding-taxes', $this->rate())->json('data.id');
        $second = $this->user->workspaces()->create(['name' => 'Second Business']);

        $this->putJson("/api/v1/withholding-taxes/{$id}", $this->rate(), $this->pick($second))->assertNotFound();
        $this->deleteJson("/api/v1/withholding-taxes/{$id}", [], $this->pick($second))->assertNotFound();
    }

    public function test_withholding_tax_is_switched_on_in_the_tax_settings_and_rides_on_the_sidebar(): void
    {
        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'later', 'withholding_tax' => true])->assertOk();

        $this->getJson('/api/v1/business-settings/taxes')->assertJsonPath('data.withholding_tax', true);
        $this->getJson('/api/v1/navigation')->assertJsonPath('data.workspace.withholding_tax', true);
    }
}
