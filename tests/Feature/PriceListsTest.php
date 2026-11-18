<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase F1: price lists - their customers, locations, currency, days
 * and priority, and rules by item, category, brand or all, from a quantity
 * up: a fixed price, a percentage off the item's price, or a markup on its
 * cost - pricing a sales form's lines, one price a line, with the list and
 * rule kept on the line; a used list switched off, never deleted. The chair
 * sells at USD 150. Wednesday 28 October 2026.
 */
class PriceListsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        $full = Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 10, 'is_active' => true, 'status' => 'available']);
        $full->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->user = $this->signIn();
        $this->setUpSales();
        $this->chair->forceFill(['purchase_price' => '80.00'])->save();
    }

    private function list(array $fields = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/sales/price-lists', [
            'name' => 'Wholesale', 'currency' => 'USD', 'is_active' => true, 'all_customers' => true, 'all_locations' => true, 'priority' => 5,
            'rules' => [
                ['target' => 'item', 'item_id' => $this->chair->id, 'min_quantity' => '1', 'method' => 'fixed', 'value' => '120'],
                ['target' => 'item', 'item_id' => $this->chair->id, 'min_quantity' => '10', 'method' => 'fixed', 'value' => '110'],
                ['target' => 'all', 'method' => 'discount', 'value' => '10'],
            ],
            ...$fields,
        ]);
    }

    /** What the lines cost by the lists, by key. */
    private function prices(array $lines, array $context = []): array
    {
        return collect($this->postJson('/api/v1/sales/prices', ['currency' => 'USD', 'date' => '2026-10-28', 'contact_id' => $this->customer->id, 'lines' => $lines, ...$context])->assertOk()->json('data'))
            ->mapWithKeys(fn (array $row) => [$row['key'] => [$row['unit_price'], $row['price_list']]])
            ->all();
    }

    public function test_a_line_takes_one_price_from_the_lists_that_hold(): void
    {
        // Sales Lite has none.
        $this->getJson('/api/v1/sales/price-lists')->assertForbidden();

        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
        $wholesale = $this->list()->assertCreated()->assertJsonPath('message', 'Price list "Wholesale" added.')->assertJsonCount(3, 'data.rules')->json('data.id');
        $this->list()->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertSame(
            ['a' => ['120.00', 'Wholesale'], 'b' => ['110.00', 'Wholesale']],
            $this->prices([['key' => 'a', 'item_id' => $this->chair->id, 'quantity' => '2'], ['key' => 'b', 'item_id' => $this->chair->id, 'quantity' => '12'], ['key' => 'c', 'item_id' => null, 'quantity' => '1']]),
        );

        // A list for this customer alone, first by priority: a markup on the chair's cost.
        $this->list(['name' => 'Key Accounts', 'priority' => 1, 'all_customers' => false, 'contact_ids' => [$this->customer->id], 'rules' => [['target' => 'all', 'method' => 'markup', 'value' => '25']]])->assertCreated();
        $this->assertSame(['a' => ['100.00', 'Key Accounts']], $this->prices([['key' => 'a', 'item_id' => $this->chair->id, 'quantity' => '2']]));
        $this->assertSame(['a' => ['120.00', 'Wholesale']], $this->prices([['key' => 'a', 'item_id' => $this->chair->id, 'quantity' => '2']], ['contact_id' => null]));

        // Another currency, or a list switched off or out of its days, prices nothing.
        $this->assertSame([], $this->prices([['key' => 'a', 'item_id' => $this->chair->id, 'quantity' => '2']], ['currency' => 'EUR']));
        $this->putJson("/api/v1/sales/price-lists/{$wholesale}", [
            'name' => 'Wholesale', 'currency' => 'USD', 'is_active' => true, 'all_customers' => true, 'all_locations' => true, 'priority' => 5, 'valid_to' => '2026-10-27',
            'rules' => [['target' => 'all', 'method' => 'discount', 'value' => '10']],
        ])->assertOk()->assertJsonPath('message', 'Price list saved.');
        $this->assertSame([], $this->prices([['key' => 'a', 'item_id' => $this->chair->id, 'quantity' => '2']], ['contact_id' => null]));
    }

    public function test_a_line_keeps_its_list_and_a_used_list_is_switched_off_not_deleted(): void
    {
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
        $list = $this->list()->assertCreated()->json('data');
        $rule = $this->getJson("/api/v1/sales/price-lists/{$list['id']}")->json('data.rules.0');

        $invoice = $this->postJson('/api/v1/sales/invoices', $this->invoice(['lines' => [[
            'item_id' => $this->chair->id, 'name' => 'Office Chair', 'quantity' => '2', 'unit_price' => '120.00',
            'price_list_id' => $list['id'], 'price_list_rule_id' => $this->ruleId($list['id'], 0),
        ]]]))->assertCreated()->json('data');

        $this->assertSame([$list['id'], 'Wholesale', '120.00'], [$invoice['lines'][0]['price_list_id'], $invoice['lines'][0]['price_list'], $invoice['lines'][0]['unit_price']]);
        $this->assertSame('item', $rule['target']);

        $this->deleteJson("/api/v1/sales/price-lists/{$list['id']}")->assertStatus(409);

        // Another business's list is not taken.
        $this->postJson('/api/v1/sales/invoices', $this->invoice(['lines' => [['name' => 'Chair', 'quantity' => '1', 'unit_price' => '1', 'price_list_id' => 999, 'price_list_rule_id' => 999]]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['lines.0.price_list_id', 'lines.0.price_list_rule_id']);
    }

    private function ruleId(int $listId, int $index): int
    {
        return \App\Models\User\PriceListRule::query()->where('price_list_id', $listId)->orderBy('position')->skip($index)->value('id');
    }
}
