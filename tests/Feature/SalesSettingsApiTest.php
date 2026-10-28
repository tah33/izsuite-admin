<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\SalesSetting;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Settings > Sales (Doc 11 section 15): what new quotations, proforma invoices
 * and invoices start with - there while Sales Lite is on.
 */
class SalesSettingsApiTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const SETTINGS = [
        'payment_terms'      => 30,
        'quotation_validity' => 15,
        'proforma_validity'  => 7,
        'line_discounts'     => true,
        'document_discounts' => false,
        'notes'              => 'Thank you for your business.',
        'terms'              => 'Goods once sold are not taken back.',
    ];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->user = $this->signIn();
    }

    public function test_a_business_starts_with_the_defaults(): void
    {
        $this->getJson('/api/v1/business-settings/sales')->assertOk()->assertExactJson(['data' => [
            'payment_terms'      => 0,
            'quotation_validity' => 30,
            'proforma_validity'  => 30,
            'line_discounts'     => true,
            'document_discounts' => true,
            'notes'              => null,
            'terms'              => null,
        ]]);

        $this->assertSame(0, SalesSetting::count());
    }

    public function test_the_settings_are_saved_and_each_save_logged_with_what_changed(): void
    {
        $this->putJson('/api/v1/business-settings/sales', self::SETTINGS)
            ->assertOk()
            ->assertJsonPath('message', 'Sales settings saved successfully.')
            ->assertJsonPath('data', self::SETTINGS);

        $this->getJson('/api/v1/business-settings/sales')->assertExactJson(['data' => self::SETTINGS]);

        $this->putJson('/api/v1/business-settings/sales', [...self::SETTINGS, 'notes' => null])->assertOk()->assertJsonPath('data.notes', null);

        $logs = ActivityLog::where('user_id', $this->user->id)->orderBy('id')->get();
        $this->assertSame(['Updated the sales settings', 'Updated the sales settings'], $logs->pluck('description')->all());
        $this->assertSame(
            [['payment_terms', 'quotation_validity', 'proforma_validity', 'document_discounts', 'notes', 'terms'], ['notes']],
            $logs->pluck('properties.fields')->all(),
        );
        $this->assertSame(1, SalesSetting::count());
    }

    public function test_values_out_of_range_are_refused(): void
    {
        $this->putJson('/api/v1/business-settings/sales', [
            ...self::SETTINGS,
            'payment_terms'      => 400,
            'quotation_validity' => 0,
            'proforma_validity'  => 366,
            'line_discounts'     => 'sometimes',
            'document_discounts' => null,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'payment_terms'      => 'Payment terms are 0 to 365 days.',
                'quotation_validity' => 'A quotation is valid for 1 to 365 days.',
                'proforma_validity'  => 'A proforma invoice is valid for 1 to 365 days.',
                'line_discounts',
                'document_discounts',
            ]);
    }

    public function test_a_business_without_sales_has_no_sales_settings(): void
    {
        $this->businessOf($this->user)->workspaceApps()->delete();

        $this->getJson('/api/v1/business-settings/sales')->assertForbidden();
        $this->putJson('/api/v1/business-settings/sales', self::SETTINGS)->assertForbidden();
    }
}
