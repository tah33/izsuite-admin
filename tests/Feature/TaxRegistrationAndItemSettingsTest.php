<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Admin\Language;
use App\Models\User\User;
use App\Services\Api\Sales\SalesSnapshots;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersExpensesLite;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 11 Phase D: the tax registration under Settings > Taxes (section 9),
 * the default expense tax under Settings > Expenses (section 17), and the
 * item settings (section 18) - warranty fields, the business's own units and
 * a default one, SKUs given automatically, and the barcode format.
 */
class TaxRegistrationAndItemSettingsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersExpensesLite;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        Language::create(['name' => 'English', 'code' => 'en', 'native_name' => 'English', 'is_default' => true, 'is_active' => true]);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        $this->offerExpensesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();
    }

    private function profile(): array
    {
        return ['business_name' => 'Uddin Traders Ltd', 'language' => 'en', 'country' => 'BD', 'timezone' => 'Asia/Dhaka', 'stock_accounting_method' => 'fifo'];
    }

    private function registration(array $overrides = []): array
    {
        return ['tax_mode' => 'enabled', 'tax_registration_status' => 'registered', 'tax_country' => 'BD', 'tax_1_name' => 'VAT', 'tax_1_no' => 'BIN-778899', ...$overrides];
    }

    public function test_the_tax_registration_is_kept_with_the_tax_settings(): void
    {
        $this->putJson('/api/v1/business-settings/taxes', $this->registration())
            ->assertJsonValidationErrors(['tax_registration_status' => 'Save your business information first.']);

        $this->postJson('/api/v1/business-settings', $this->profile())->assertOk();

        $this->putJson('/api/v1/business-settings/taxes', $this->registration(['tax_registration_status' => 'maybe', 'tax_country' => 'XX']))
            ->assertJsonValidationErrors([
                'tax_registration_status' => 'Choose whether the business is registered for tax.',
                'tax_country'             => 'Choose a country from the list.',
            ]);

        $this->putJson('/api/v1/business-settings/taxes', $this->registration())
            ->assertOk()
            ->assertJsonPath('data.tax_registration_status', 'registered')
            ->assertJsonPath('data.tax_country', 'BD')
            ->assertJsonPath('data.tax_1_no', 'BIN-778899')
            ->assertJsonPath('data.tax_2_no', null);

        // The business settings no longer carry the numbers - and a save of them leaves them be.
        $this->getJson('/api/v1/business-settings')->assertOk()->assertJsonMissingPath('data.tax_1_no');
        $this->postJson('/api/v1/business-settings', [...$this->profile(), 'tax_1_no' => 'OTHER'])->assertOk();

        $this->getJson('/api/v1/business-settings/taxes')->assertJsonPath('data.tax_1_no', 'BIN-778899');
    }

    public function test_documents_print_the_tax_numbers_only_while_the_business_is_registered(): void
    {
        $this->postJson('/api/v1/business-settings', $this->profile())->assertOk();
        $this->putJson('/api/v1/business-settings/taxes', $this->registration())->assertOk();

        $business = $this->businessOf($this->user)->fresh();

        $this->assertSame([['name' => 'VAT', 'number' => 'BIN-778899']], app(SalesSnapshots::class)->business($business, null)['tax_numbers']);

        $this->putJson('/api/v1/business-settings/taxes', $this->registration(['tax_registration_status' => 'not_registered']))->assertOk();

        $this->assertSame([], app(SalesSnapshots::class)->business($business, null)['tax_numbers']);
        $this->getJson('/api/v1/business-settings/taxes')->assertJsonPath('data.tax_1_no', 'BIN-778899');
    }

    public function test_the_default_expense_tax_is_set_from_the_expense_settings_too(): void
    {
        $rate     = $this->businessOf($this->user)->taxRates()->create(['name' => 'Input VAT', 'code' => 'IVAT', 'rate' => '15', 'treatment' => 'standard', 'for_expenses' => true, 'effective_from' => '2026-01-01']);
        $settings = ['payment_terms' => 30, 'notes' => null, 'duplicate_check' => true];

        $this->getJson('/api/v1/business-settings/expenses')->assertOk()->assertJsonPath('data.default_expense_tax_id', null);

        $this->putJson('/api/v1/business-settings/expenses', [...$settings, 'default_expense_tax_id' => $this->vat->id])
            ->assertJsonValidationErrors(['default_expense_tax_id' => 'Choose one of your active expense taxes.']);

        $this->putJson('/api/v1/business-settings/expenses', [...$settings, 'default_expense_tax_id' => $rate->id])
            ->assertOk()
            ->assertJsonPath('data.default_expense_tax_id', $rate->id);

        $this->getJson('/api/v1/business-settings/taxes')->assertJsonPath('data.default_expense_tax_id', $rate->id);

        // Left out, it stays; null clears it.
        $this->putJson('/api/v1/business-settings/expenses', $settings)->assertJsonPath('data.default_expense_tax_id', $rate->id);
        $this->putJson('/api/v1/business-settings/expenses', [...$settings, 'default_expense_tax_id' => null])->assertJsonPath('data.default_expense_tax_id', null);
    }

    public function test_item_settings_start_as_items_were_and_keep_units_items_have(): void
    {
        $this->getJson('/api/v1/business-settings/items')->assertOk()->assertExactJson(['data' => [
            'brands_enabled' => false, 'warranty_enabled' => true, 'default_unit' => null, 'units' => [], 'units_in_use' => [],
            'sku_auto' => false, 'sku_prefix' => 'SKU-', 'barcode_format' => 'any',
        ]]);

        $this->putJson('/api/v1/business-settings/items', [
            'brands_enabled' => true, 'warranty_enabled' => false, 'default_unit' => 'Tray', 'units' => [' Tray ', 'Crate'],
            'sku_auto' => true, 'sku_prefix' => 'CHR-', 'barcode_format' => 'ean13',
        ])
            ->assertOk()
            ->assertJsonPath('data.units', ['Tray', 'Crate'])
            ->assertJsonPath('data.default_unit', 'Tray');

        $this->assertFalse($this->getJson('/api/v1/navigation')->json('data.workspace.warranty_enabled'));

        $this->businessOf($this->user)->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Eggs', 'unit' => 'Tray']);

        $this->getJson('/api/v1/business-settings/items')->assertJsonPath('data.units_in_use', ['Tray']);
        $this->putJson('/api/v1/business-settings/items', ['brands_enabled' => true, 'units' => ['Crate']])
            ->assertJsonValidationErrors(['units' => 'Items are measured in "Tray", so it stays on the list.']);
        $this->putJson('/api/v1/business-settings/items', ['brands_enabled' => true, 'units' => ['Tray', 'tray']])->assertJsonValidationErrors('units.1');
        $this->putJson('/api/v1/business-settings/items', ['brands_enabled' => true, 'sku_prefix' => 'S K U', 'barcode_format' => 'qr'])
            ->assertJsonValidationErrors(['sku_prefix' => 'A SKU prefix is letters, digits and - / _ . only.', 'barcode_format' => 'Choose a barcode format from the list.']);
    }

    public function test_new_items_are_given_the_next_sku_while_skus_are_given_automatically(): void
    {
        $item = fn (array $fields = []) => $this->postJson('/api/v1/items', ['type' => 'physical', 'usage' => 'both', 'name' => 'Desk', 'unit' => 'Piece', ...$fields]);

        $this->assertNull($item()->assertCreated()->json('data.sku'));

        $this->putJson('/api/v1/business-settings/items', ['brands_enabled' => false, 'sku_auto' => true, 'sku_prefix' => 'CHR-'])->assertOk();

        $this->assertSame('CHR-00001', $item()->json('data.sku'));
        $this->assertSame('CHR-00002', $item(['sku' => 'CHR-00002'])->json('data.sku'));
        // Its own SKU taken, the next one given skips it.
        $this->assertSame('CHR-00003', $item()->json('data.sku'));
        $this->assertSame('MY-OWN', $item(['sku' => 'MY-OWN'])->json('data.sku'));
    }

    public function test_barcodes_follow_the_business_format(): void
    {
        $old  = $this->businessOf($this->user)->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Lamp', 'unit' => 'Piece', 'barcode' => '12345']);
        $item = ['type' => 'physical', 'usage' => 'both', 'name' => 'Desk', 'unit' => 'Piece'];

        $this->putJson('/api/v1/business-settings/items', ['brands_enabled' => false, 'barcode_format' => 'ean13'])->assertOk();

        $this->postJson('/api/v1/items', [...$item, 'barcode' => '4006381333932'])
            ->assertJsonValidationErrors(['barcode' => 'Enter an EAN-13 barcode: 13 digits, the last its check digit.']);
        $this->postJson('/api/v1/items', [...$item, 'barcode' => '4006381333931'])->assertCreated();

        // A barcode an item already had stays, so the rest of it can be edited.
        $this->putJson("/api/v1/items/{$old->id}", ['type' => 'physical', 'usage' => 'both', 'name' => 'Desk Lamp', 'unit' => 'Piece', 'barcode' => '12345'])->assertOk();

        $this->putJson('/api/v1/business-settings/items', ['brands_enabled' => false, 'barcode_format' => 'upca'])->assertOk();
        $this->postJson('/api/v1/items', [...$item, 'name' => 'Chair', 'barcode' => '036000291452'])->assertCreated();
        $this->postJson('/api/v1/items', [...$item, 'name' => 'Stool', 'barcode' => '036000291453'])->assertJsonValidationErrors('barcode');
    }

    public function test_warranties_switched_off_leave_new_sales_lines_without_one(): void
    {
        $this->chair->forceFill(['warranty_available' => true, 'warranty_period' => 12, 'warranty_unit' => 'months', 'warranty_start_rule' => 'invoice_date'])->save();

        $id = $this->draft('invoices');

        $this->assertSame(12, $this->getJson("/api/v1/sales/invoices/{$id}")->json('data.lines.0.warranty.period'));

        $this->putJson('/api/v1/business-settings/items', ['brands_enabled' => false, 'warranty_enabled' => false])->assertOk();

        $id = $this->draft('invoices');

        $this->assertNull($this->getJson("/api/v1/sales/invoices/{$id}")->json('data.lines.0.warranty'));
        $this->assertTrue($this->chair->fresh()->warranty_available);
    }
}
