<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\Item;
use App\Models\User\ItemBrand;
use App\Models\User\ItemCategory;
use App\Models\User\ItemComponent;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Items to and from CSV (Doc 04 section 17). Combos go out with their
 * components but never come in; categories and brands come in by name.
 */
class ItemImportExportTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        Storage::fake('local');

        $this->user = $this->signIn();
    }

    private function csv(TestResponse $response): array
    {
        $content = $response->streamedContent();

        return array_map(fn (string $line) => str_getcsv($line, ',', '"', ''), preg_split('/\r?\n/', trim(substr($content, 3))));
    }

    private function row(array $csv, int $index): array
    {
        return array_combine($csv[0], $csv[$index]);
    }

    private function itemOf(array $fields): Item
    {
        return $this->businessOf($this->user)->items()->create(['type' => 'physical', 'usage' => 'both', 'unit' => 'Piece', ...$fields]);
    }

    public function test_the_export_lists_combos_with_their_components_and_brands_only_while_used(): void
    {
        $business = $this->businessOf($this->user);
        $chair    = $this->itemOf(['name' => 'Office Chair', 'sku' => 'CHR-001', 'sale_price' => '149.99', 'brand_id' => $business->itemBrands()->create(['name' => 'Ergo'])->id]);
        $lamp     = $this->itemOf(['name' => 'Desk Lamp', 'sale_price' => '10.01']);
        $combo    = $this->itemOf(['name' => 'Home Office Set', 'type' => 'combo', 'usage' => 'sales', 'unit' => 'Set', 'combo_pricing' => 'sum', 'combo_display' => 'combo_only']);
        ItemComponent::create(['combo_id' => $combo->id, 'component_id' => $chair->id, 'quantity' => '1.5', 'sort_order' => 0]);
        ItemComponent::create(['combo_id' => $combo->id, 'component_id' => $lamp->id, 'quantity' => '1', 'sort_order' => 1]);

        $csv = $this->csv($this->get('/api/v1/items/export', ['Accept' => 'application/json'])->assertOk());

        $this->assertNotContains('Brand', $csv[0]);
        $this->assertSame(['Desk Lamp', 'Home Office Set', 'Office Chair'], array_column(array_slice($csv, 1), array_search('Item Name', $csv[0])));
        $this->assertSame([
            'Item Number' => 'I-00003', 'Item Type' => 'Combo', 'Item Usage' => 'Sales', 'Sales Price' => '235.00',
            'Pricing Method' => 'Sum of Component Prices', 'Components' => 'I-00001 x 1.5; I-00002 x 1',
        ], array_intersect_key($this->row($csv, 2), array_flip(['Item Number', 'Item Type', 'Item Usage', 'Sales Price', 'Pricing Method', 'Components'])));

        $business->forceFill(['brands_enabled' => true])->save();

        $csv = $this->csv($this->get('/api/v1/items/export?type=physical', ['Accept' => 'application/json']));

        $this->assertSame(['Desk Lamp', 'Office Chair'], array_column(array_slice($csv, 1), array_search('Item Name', $csv[0])));
        $this->assertSame('Ergo', $this->row($csv, 2)['Brand']);
    }

    public function test_an_import_reads_types_prices_and_labels_and_keeps_skus_unique(): void
    {
        $this->itemOf(['name' => 'Office Chair', 'sku' => 'OLD-1']);

        $content = implode("\n", [
            'Item Name,Item Type,SKU,Barcode,Category,Brand,Price,Cost,Unit,Warranty Period,Delivery Method',
            'Standing Desk,Physical Product,DSK-100,,Furniture,Ergo,"1,234.50",$800,,12,',
            'Design Course,Digital,,,Courses,,49,,,,License Key',
            'Assembly,Service,,,,,25,,,,',
            'Office Set,Combo,,,,,,,,,',
            'Desk Mat,Physical,DSK-100,,,,10,,,,',
            'Office Chair,Physical,CHR-001,,,,,,,,',
            'Lamp,Gadget,,,,,,,,,',
            'Bookshelf,physical product,,,furniture,,99.5,,Piece,,',
            'Old Stock,Physical,OLD-1,,,,5,,,,',
        ]);

        $import = $this->post('/api/v1/imports', ['kind' => 'items', 'file' => UploadedFile::fake()->createWithContent('items.csv', $content)], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        $this->assertSame(
            ['type' => 1, 'name' => 0, 'unit' => 8, 'sku' => 2, 'barcode' => 3, 'category' => 4, 'brand' => 5, 'sale_price' => 6, 'purchase_price' => 7, 'delivery_method' => 10, 'warranty_period' => 9],
            array_filter($import['mapping'], fn ($column) => $column !== null),
        );

        $this->putJson("/api/v1/imports/{$import['id']}/mapping", ['mapping' => $import['mapping']])
            ->assertJsonPath('data.summary', ['rows' => 9, 'ready' => 4, 'duplicates' => 1, 'errors' => 4]);

        $errors = collect($this->getJson("/api/v1/imports/{$import['id']}/rows?show=errors")->json('data'))->mapWithKeys(fn (array $row) => [$row['line'] => $row['errors']])->all();

        $this->assertSame([
            5  => ['type' => 'Combos are not imported: make them in the app, from items you already have.'],
            6  => ['sku' => 'Row 2 of this file has the same SKU.'],
            8  => ['type' => 'Write Physical Product, Digital Product or Service.'],
            10 => ['sku' => 'Another item of this business already has that SKU.'],
        ], $errors);

        $this->postJson("/api/v1/imports/{$import['id']}/run")->assertJsonPath('data.result', ['created' => 4, 'skipped' => 5, 'failed' => 0]);

        $desk = Item::where('name', 'Standing Desk')->sole();
        $this->assertSame(
            ['I-00002', 'physical', 'both', 'Piece', '1234.50', '800.00', true, 12, 'months', 'invoice_date', 'Furniture', 'Ergo'],
            [$desk->item_number, $desk->type, $desk->usage, $desk->unit, $desk->sale_price, $desk->purchase_price, $desk->warranty_available, $desk->warranty_period, $desk->warranty_unit, $desk->warranty_start_rule, $desk->category->name, $desk->brand->name],
        );

        $course = Item::where('name', 'Design Course')->sole();
        $this->assertSame(['digital', 'Licence', 'license_key', '49.00'], [$course->type, $course->unit, $course->delivery_method, $course->sale_price]);
        $this->assertSame(['service', 'Service'], [Item::where('name', 'Assembly')->value('type'), Item::where('name', 'Assembly')->value('unit')]);

        // "furniture" is the category the first row made.
        $this->assertSame(['Furniture', 'Courses'], ItemCategory::orderBy('id')->pluck('name')->all());
        $this->assertSame($desk->category_id, Item::where('name', 'Bookshelf')->value('category_id'));
        $this->assertSame(['Ergo'], ItemBrand::pluck('name')->all());

        $this->assertSame(['Imported 4 items from "items.csv"'], ActivityLog::where('user_id', $this->user->id)->pluck('description')->all());
    }

    public function test_the_template_has_every_field_and_an_example(): void
    {
        $csv = $this->csv($this->get('/api/v1/imports/items/template', ['Accept' => 'application/json'])->assertOk());

        $this->assertSame(['Item Type', 'Item Usage', 'Item Name', 'Unit of Measure'], array_slice($csv[0], 0, 4));
        $this->assertSame('Office Chair', $this->row($csv, 1)['Item Name']);
    }
}
