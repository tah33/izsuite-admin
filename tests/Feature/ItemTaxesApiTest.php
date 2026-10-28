<?php

namespace Tests\Feature;

use App\Models\User\Item;
use App\Models\User\TaxRate;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * An item's default sales and purchase tax (Doc 04 sections 8 to 10): rates
 * of its business for that side, shown on its profile, and in and out of CSV
 * by their codes while the business uses taxes.
 */
class ItemTaxesApiTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const CHAIR = ['type' => 'physical', 'usage' => 'both', 'name' => 'Office Chair', 'unit' => 'Piece'];

    private User $user;

    private TaxRate $vat;

    private TaxRate $input;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        Storage::fake('local');

        $this->user = $this->signIn();

        $business = $this->businessOf($this->user);
        $business->forceFill(['tax_mode' => 'enabled'])->save();

        $this->vat   = $business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT15', 'rate' => '15', 'treatment' => 'standard', 'for_sales' => true, 'for_purchases' => true, 'effective_from' => '2026-01-01']);
        $this->input = $business->taxRates()->create(['name' => 'Input VAT', 'code' => 'IN5', 'rate' => '5', 'treatment' => 'standard', 'for_purchases' => true, 'effective_from' => '2026-01-01']);
    }

    private function csv(TestResponse $response): array
    {
        return array_map(fn (string $line) => str_getcsv($line, ',', '"', ''), preg_split('/\r?\n/', trim(substr($response->streamedContent(), 3))));
    }

    public function test_an_item_starts_its_sales_and_purchases_with_its_own_taxes(): void
    {
        $id = $this->postJson('/api/v1/items', [...self::CHAIR, 'sales_tax_id' => $this->vat->id, 'purchase_tax_id' => $this->input->id])
            ->assertCreated()
            ->assertJsonPath('data.sales_tax_id', $this->vat->id)
            ->assertJsonPath('data.sales_tax', ['name' => 'VAT', 'rate' => '15.0000', 'treatment' => 'standard', 'is_active' => true])
            ->assertJsonPath('data.purchase_tax.name', 'Input VAT')
            ->json('data.id');

        // Left out of an update - a form hides them while taxes are off - they are kept.
        $this->putJson("/api/v1/items/{$id}", [...self::CHAIR, 'name' => 'Office Chair Pro'])
            ->assertOk()
            ->assertJsonPath('data.sales_tax_id', $this->vat->id)
            ->assertJsonPath('data.purchase_tax_id', $this->input->id);

        $this->getJson("/api/v1/items/{$id}")->assertJsonPath('data.purchase_tax.rate', '5.0000');
    }

    public function test_a_tax_is_a_rate_of_the_business_for_its_side(): void
    {
        $elsewhere = $this->user->workspaces()->create(['name' => 'Second Business'])->taxRates()->create([
            'name' => 'VAT', 'code' => 'VAT15', 'rate' => '15', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01',
        ]);

        $this->postJson('/api/v1/items', [...self::CHAIR, 'sales_tax_id' => $this->input->id])
            ->assertJsonValidationErrors(['sales_tax_id' => 'Choose one of this business\'s sales taxes.']);
        $this->postJson('/api/v1/items', [...self::CHAIR, 'sales_tax_id' => $elsewhere->id])->assertJsonValidationErrors('sales_tax_id');

        $this->assertSame(0, Item::count());
    }

    public function test_a_tax_switched_off_since_may_stay_but_is_not_newly_chosen(): void
    {
        $id = $this->postJson('/api/v1/items', [...self::CHAIR, 'sales_tax_id' => $this->vat->id])->json('data.id');

        $this->vat->update(['is_active' => false]);

        $this->putJson("/api/v1/items/{$id}", [...self::CHAIR, 'name' => 'Office Chair Pro', 'sales_tax_id' => $this->vat->id])
            ->assertOk()
            ->assertJsonPath('data.sales_tax.is_active', false);

        $this->postJson('/api/v1/items', [...self::CHAIR, 'name' => 'Desk', 'sales_tax_id' => $this->vat->id])
            ->assertJsonValidationErrors(['sales_tax_id' => 'VAT is inactive. Choose an active tax.']);
    }

    public function test_taxes_go_in_and_out_of_csv_by_code_while_taxes_are_on(): void
    {
        $this->businessOf($this->user)->items()->create([...self::CHAIR, 'sales_tax_id' => $this->vat->id]);

        $csv = $this->csv($this->get('/api/v1/items/export', ['Accept' => 'application/json'])->assertOk());
        $row = array_combine($csv[0], $csv[1]);
        $this->assertSame(['VAT15', ''], [$row['Sales Tax'], $row['Purchase Tax']]);

        $template = $this->csv($this->get('/api/v1/imports/items/template', ['Accept' => 'application/json'])->assertOk());
        $this->assertContains('Sales Tax', $template[0]);
        $this->assertContains('Purchase Tax', $template[0]);

        $content = implode("\n", [
            'Item Name,Item Type,Sales Tax,Purchase Tax',
            'Standing Desk,Physical,vat15,IN5',
            'Desk Lamp,Physical,GST,',
            'Desk Mat,Physical,IN5,',
        ]);

        $import = $this->post('/api/v1/imports', ['kind' => 'items', 'file' => UploadedFile::fake()->createWithContent('items.csv', $content)], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        $this->putJson("/api/v1/imports/{$import['id']}/mapping", ['mapping' => $import['mapping']])
            ->assertJsonPath('data.summary', ['rows' => 3, 'ready' => 1, 'duplicates' => 0, 'errors' => 2]);

        $errors = collect($this->getJson("/api/v1/imports/{$import['id']}/rows?show=errors")->json('data'))->mapWithKeys(fn (array $row) => [$row['line'] => $row['errors']])->all();

        $this->assertSame([
            3 => ['sales_tax' => 'There is no tax with the code "GST". Add it under Settings > Taxes first.'],
            4 => ['sales_tax' => 'IN5 is not a sales tax.'],
        ], $errors);

        $this->postJson("/api/v1/imports/{$import['id']}/run")->assertJsonPath('data.result.created', 1);

        $desk = Item::where('name', 'Standing Desk')->sole();
        $this->assertSame([$this->vat->id, $this->input->id], [$desk->sales_tax_id, $desk->purchase_tax_id]);

        // Taxes off: no tax columns.
        $this->businessOf($this->user)->forceFill(['tax_mode' => 'disabled'])->save();

        $this->assertNotContains('Sales Tax', $this->csv($this->get('/api/v1/items/export', ['Accept' => 'application/json']))[0]);
    }
}
