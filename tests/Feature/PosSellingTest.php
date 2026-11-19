<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\Location;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase C1: the selling screen (sections 18.7 to 18.14) - in the
 * cashier's own open session at Front Counter (R1), in Dhaka: the walk-in
 * customer, the category tiles, the most-sold items, the items found by
 * name, SKU, barcode or category - a scanned code found exactly - and the
 * cart priced from the customer's own price list, else the register's, else
 * the item, taxed as Settings → Taxes says and totalled.
 *
 * Black Tea (Drinks, TEA-1) sells at 3.50, Green Tea (Drinks, TEA-2) at
 * 4.00 and the Mug (Kitchen) at 8.00; the Old Mug is switched off and the
 * Office Chair (Furniture) is bought only. Thursday 19 November 2026.
 */
class PosSellingTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const SELL = '/api/v1/pos/sell';

    private const REGISTERS = '/api/v1/pos/registers';

    private User $user;

    private Workspace $business;

    private Location $dhaka;

    private Location $chattogram;

    private Contact $walkIn;

    private Contact $rahman;

    private Item $tea;

    private Item $greenTea;

    private Item $mug;

    private Item $oldMug;

    private Item $chair;

    private int $cash;

    private array $register;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        $category = Application::sole()->category_id;

        foreach ([['sales', 'Sales', 'sales', 10], ['pos', 'Point of Sale', 'pos', 30]] as [$key, $name, $family, $order]) {
            Application::create(['app_key' => $key, 'name' => $name, 'family_key' => $family, 'edition' => 'full', 'category_id' => $category, 'sort_order' => $order, 'is_active' => true, 'status' => 'available'])
                ->packages()->attach(Package::pluck('id'));
        }

        $this->travelTo(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'additional_currencies' => ['EUR'], 'tax_mode' => 'later'])->save();
        $this->dhaka      = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->chattogram = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $this->cash     = (int) $this->getJson(self::REGISTERS.'/options')->assertOk()->json('data.payment_methods.0.id');
        $this->register = $this->addRegister('Front Counter', 'R1', [$this->user->id]);

        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->rahman = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Rahman Traders']);
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $this->walkIn->id]);

        $drinks    = $this->business->itemCategories()->create(['name' => 'Drinks']);
        $kitchen   = $this->business->itemCategories()->create(['name' => 'Kitchen']);
        $furniture = $this->business->itemCategories()->create(['name' => 'Furniture']);

        $this->tea      = $this->item('Black Tea', '3.50', ['category_id' => $drinks->id, 'sku' => 'TEA-1', 'barcode' => '4006381333931']);
        $this->greenTea = $this->item('Green Tea', '4.00', ['category_id' => $drinks->id, 'sku' => 'TEA-2']);
        $this->mug      = $this->item('Mug', '8.00', ['category_id' => $kitchen->id]);
        $this->oldMug   = $this->item('Old Mug', '6.00', ['category_id' => $kitchen->id, 'is_active' => false]);
        $this->chair    = $this->item('Office Chair', '150.00', ['category_id' => $furniture->id, 'usage' => 'purchase']);
    }

    private function item(string $name, ?string $price, array $fields = []): Item
    {
        return $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => $name, 'unit' => 'Piece', 'sale_price' => $price, ...$fields]);
    }

    private function addRegister(string $name, string $code, array $cashierIds): array
    {
        return $this->postJson(self::REGISTERS, [
            'name' => $name, 'code' => $code, 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => $cashierIds,
        ])->assertCreated()->json('data');
    }

    /** Front Counter's form as it was saved, changed as $fields say. */
    private function saveRegister(array $fields): void
    {
        $this->register = $this->putJson(self::REGISTERS."/{$this->register['id']}", [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash],
            'cashier_ids' => array_column($this->register['cashiers'], 'id'), ...$fields,
        ])->assertOk()->json('data');
    }

    /** A session opened by whoever is signed in, at $register - Front Counter by default; its id. */
    private function openSession(?array $register = null): int
    {
        return $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => ($register ?? $this->register)['id'], 'opening_float' => '50'])->assertCreated()->json('data.id');
    }

    /** The cart priced: $lines, each [item, quantity], for $contact - nobody, when null. */
    private function quote(array $lines, ?Contact $contact = null): TestResponse
    {
        return $this->getJson(self::SELL.'/quote?'.http_build_query([
            'contact_id' => $contact?->id,
            'lines'      => array_map(fn (array $line) => ['item_id' => $line[0]->id, 'quantity' => $line[1]], $lines),
        ]));
    }

    /** What a search finds. */
    private function found(array $query): array
    {
        return $this->getJson(self::SELL.'/items?'.http_build_query($query))->assertOk()->json('data');
    }

    /** An invoice to Rahman Traders at $location on $date, issued, of $items - each [item, quantity] at its price. */
    private function sold(Location $location, string $date, array $items): void
    {
        $id = $this->postJson('/api/v1/sales/invoices', [
            'contact_id' => $this->rahman->id, 'location_id' => $location->id, 'document_date' => $date, 'currency' => 'USD',
            'lines'      => array_map(fn (array $row) => ['item_id' => $row[0]->id, 'name' => $row[0]->name, 'quantity' => $row[1], 'unit' => 'Piece', 'unit_price' => (string) $row[0]->sale_price], $items),
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/sales/invoices/{$id}/issue")->assertOk();
    }

    /** A price list of the full Sales app's, for every customer and location unless $fields say otherwise; its id. */
    private function priceList(string $name, array $rules, array $fields = []): int
    {
        return $this->postJson('/api/v1/sales/price-lists', [
            'name' => $name, 'currency' => 'USD', 'is_active' => true, 'all_customers' => true, 'all_locations' => true, 'priority' => 5, 'rules' => $rules, ...$fields,
        ])->assertCreated()->json('data.id');
    }

    /** A member whose role holds $permissions, as the role editor saves them, at every location. */
    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => Permissions::expand($permissions)]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_the_screen_starts_with_the_register_the_walk_in_customer_the_categories_and_the_most_sold_items(): void
    {
        // No session of one's own open: that alone.
        $this->getJson(self::SELL)->assertOk()->assertExactJson(['data' => ['session' => null]]);

        // In the last 30 days at Dhaka: the tea on three invoices, the mug on two, the green tea on one - and the old mug before it was switched off.
        $this->oldMug->update(['is_active' => true]);
        $this->sold($this->dhaka, '2026-11-18', [[$this->tea, '1'], [$this->mug, '5'], [$this->oldMug, '9']]);
        $this->sold($this->dhaka, '2026-11-10', [[$this->tea, '2'], [$this->mug, '1'], [$this->oldMug, '1']]);
        $this->sold($this->dhaka, '2026-10-25', [[$this->tea, '1'], [$this->greenTea, '1']]);
        $this->oldMug->update(['is_active' => false]);
        // Elsewhere, or longer ago: not counted.
        $this->sold($this->chattogram, '2026-11-18', [[$this->greenTea, '30']]);
        $this->sold($this->dhaka, '2026-10-01', [[$this->greenTea, '30']]);

        $session = $this->openSession();
        $screen  = $this->getJson(self::SELL)->assertOk()->json('data');

        $this->assertSame(['id' => $session, 'number' => 'S-00001'], $screen['session']);
        $this->assertSame(['name' => 'Front Counter', 'code' => 'R1', 'currency' => 'USD', 'location' => 'Dhaka', 'price_list' => null], $screen['register']);
        $this->assertSame(['id' => $this->walkIn->id, 'name' => 'Walk-in Customer'], $screen['walk_in']);
        // Furniture holds nothing to sell.
        $this->assertSame(['Drinks', 'Kitchen'], array_column($screen['categories'], 'name'));
        $this->assertSame(
            [['Black Tea', '3.50'], ['Mug', '8.00'], ['Green Tea', '4.00']],
            array_map(fn (array $row) => [$row['name'], $row['price']], $screen['frequent']),
        );
        $this->assertSame(['exclusive', false], [$screen['price_tax_mode'], $screen['rate_missing']]);

        // A walk-in customer who can no longer be sold to is none.
        $this->walkIn->update(['is_active' => false]);
        $this->assertNull($this->getJson(self::SELL)->json('data.walk_in'));
    }

    public function test_a_search_finds_items_to_sell_and_a_scanned_code_finds_its_item_exactly(): void
    {
        $this->openSession();

        // By name, alphabetical - the old mug is switched off.
        $this->assertSame(['Black Tea', 'Green Tea'], array_column($this->found(['search' => 'tea'])['items'], 'name'));
        $this->assertSame(['Mug'], array_column($this->found(['search' => 'mug'])['items'], 'name'));
        // By category, its name or its tile - and never what is only bought.
        $this->assertSame(['Black Tea', 'Green Tea'], array_column($this->found(['search' => 'drinks'])['items'], 'name'));
        $this->assertSame(['Mug'], array_column($this->found(['category_id' => $this->mug->category_id])['items'], 'name'));
        $this->assertSame([], $this->found(['search' => 'chair'])['items']);
        $this->assertSame(['Black Tea', 'Green Tea', 'Mug'], array_column($this->found([])['items'], 'name'));

        // A scanner types the barcode, or the SKU in any case: that item, exactly.
        $this->assertSame($this->tea->id, $this->found(['search' => '4006381333931'])['exact']['id']);
        $this->assertSame('Green Tea', $this->found(['search' => 'tea-2'])['exact']['name']);
        $this->assertNull($this->found(['search' => 'tea'])['exact']);
        $this->assertSame(
            ['id' => $this->tea->id, 'name' => 'Black Tea', 'sku' => 'TEA-1', 'type' => 'physical', 'unit' => 'Piece', 'category' => 'Drinks', 'image' => null, 'price' => '3.50'],
            $this->found(['search' => 'TEA-1'])['exact'],
        );

        // Brands count once they are on.
        $brand = $this->business->itemBrands()->create(['name' => 'Ispahani']);
        $this->greenTea->update(['brand_id' => $brand->id]);
        $this->assertSame([], $this->found(['search' => 'ispahani'])['items']);
        $this->business->forceFill(['brands_enabled' => true])->save();
        $this->assertSame(['Green Tea'], array_column($this->found(['search' => 'ispahani'])['items'], 'name'));
    }

    public function test_the_cart_is_priced_taxed_and_totalled_as_settings_say(): void
    {
        $this->openSession();

        // Taxes not used: none. Two teas, a mug and a half; a set priced as its parts - two teas and a mug.
        $set = $this->item('Tea Set', null, ['type' => 'combo', 'usage' => 'sales', 'combo_pricing' => 'sum', 'combo_display' => 'combo_only']);
        $set->components()->createMany([['component_id' => $this->tea->id, 'quantity' => '2', 'sort_order' => 1], ['component_id' => $this->mug->id, 'quantity' => '1', 'sort_order' => 2]]);

        $quote = $this->quote([[$this->tea, '2'], [$this->mug, '1.5'], [$set, '1']])->assertOk()->json('data');
        $this->assertSame(['34.00', '34.00', []], [$quote['subtotal'], $quote['grand_total'], $quote['taxes']]);
        $this->assertSame(
            [[$this->tea->id, '3.50', 'item', null, '7.00'], [$this->mug->id, '8.00', 'item', null, '12.00'], [$set->id, '15.00', 'item', null, '15.00']],
            array_map(fn (array $line) => [$line['item_id'], $line['price'], $line['price_source'], $line['price_list'], $line['amount']], $quote['lines']),
        );

        // Taxes on: VAT at 15% by default, the mug at its own 5%, prices without tax.
        $this->business->forceFill(['tax_mode' => 'enabled'])->save();
        $this->business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT15', 'rate' => '15', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01'])
            ->forceFill(['default_for_sales' => true])->save();
        $low = $this->business->taxRates()->create(['name' => 'Reduced', 'code' => 'R5', 'rate' => '5', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01']);
        $this->mug->update(['sales_tax_id' => $low->id]);

        // The teas 7.00 + VAT 1.05; the mugs 12.00 + 0.60.
        $quote = $this->quote([[$this->tea, '2'], [$this->mug, '1.5']])->assertOk()->json('data');
        $this->assertSame(['19.00', '20.65'], [$quote['subtotal'], $quote['grand_total']]);
        $this->assertSame([['VAT', '15.0000', '1.05'], ['Reduced', '5.0000', '0.60']], array_map(fn (array $tax) => [$tax['name'], $tax['rate'], $tax['amount']], $quote['taxes']));

        // A rate switched off is not used: the default instead.
        $low->update(['is_active' => false]);
        $this->assertSame(['VAT'], array_column($this->quote([[$this->mug, '1']])->json('data.taxes'), 'name'));

        // A customer with a treatment of their own takes its rate - here, none.
        $this->rahman->update(['tax_treatment' => 'exempt']);
        $quote = $this->quote([[$this->mug, '1']], $this->rahman)->assertOk()->json('data');
        $this->assertSame(['8.00', []], [$quote['grand_total'], $quote['taxes']]);

        // Prices with tax: the total is the shelf price, its VAT inside it.
        $this->business->forceFill(['price_tax_mode' => 'inclusive'])->save();
        $quote = $this->quote([[$this->tea, '2']])->json('data');
        $this->assertSame(['7.00', '0.91', '7.00'], [$quote['subtotal'], $quote['taxes'][0]['amount'], $quote['grand_total']]);
    }

    public function test_prices_come_from_the_customers_own_list_else_the_registers_else_the_item(): void
    {
        // Price lists are the full Sales app's.
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
        $shop = $this->priceList('Shop Prices', [['target' => 'item', 'item_id' => $this->tea->id, 'min_quantity' => '1', 'method' => 'fixed', 'value' => '3.00']]);
        $this->priceList('Rahman Prices', [
            ['target' => 'item', 'item_id' => $this->tea->id, 'min_quantity' => '1', 'method' => 'fixed', 'value' => '2.80'],
            ['target' => 'item', 'item_id' => $this->tea->id, 'min_quantity' => '10', 'method' => 'fixed', 'value' => '2.50'],
        ], ['all_customers' => false, 'contact_ids' => [$this->rahman->id]]);
        // Another list, for every customer, first by priority: the register does not sell by it.
        $this->priceList('Wholesale', [['target' => 'all', 'method' => 'discount', 'value' => '50']], ['priority' => 1]);
        $this->saveRegister(['price_list_id' => $shop]);
        $this->openSession();

        $priced = fn (array $lines, ?Contact $contact = null) => array_map(
            fn (array $line) => [$line['price'], $line['price_source'], $line['price_list']],
            $this->quote($lines, $contact)->assertOk()->json('data.lines'),
        );

        // Nobody in particular: the register's list, else the item's price.
        $this->assertSame([['3.00', 'price_list', 'Shop Prices'], ['8.00', 'item', null]], $priced([[$this->tea, '1'], [$this->mug, '1']]));
        // Rahman: their own list first - from 10 on, its price for 10.
        $this->assertSame(
            [['2.80', 'customer_price_list', 'Rahman Prices'], ['2.50', 'customer_price_list', 'Rahman Prices'], ['8.00', 'item', null]],
            $priced([[$this->tea, '2'], [$this->tea, '10'], [$this->mug, '1']], $this->rahman),
        );

        // The screen and the search show the register's prices.
        $this->assertSame('Shop Prices', $this->getJson(self::SELL)->json('data.register.price_list'));
        $this->assertSame('3.00', $this->found(['search' => 'TEA-1'])['exact']['price']);
    }

    public function test_a_register_in_another_currency_sells_at_todays_rate(): void
    {
        $euros  = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Euro Till', 'currency' => 'EUR']);
        $method = $this->postJson('/api/v1/business-settings/pos/payment-methods', ['name' => 'Euro Cash', 'type' => 'cash', 'payment_account_id' => $euros->id, 'gives_change' => true])->assertCreated()->json('data.id');
        $this->saveRegister(['currency' => 'EUR', 'payment_method_ids' => [$method]]);
        $this->openSession();

        // No rate for the euro today: nothing is priced.
        $this->assertTrue($this->getJson(self::SELL)->assertOk()->json('data.rate_missing'));
        $refusal = 'There is no exchange rate for EUR today: add one in Settings → Currencies, and the register\'s prices follow.';
        $this->getJson(self::SELL.'/items?search=tea')->assertStatus(409)->assertJsonPath('message', $refusal);
        $this->quote([[$this->tea, '1']])->assertStatus(409)->assertJsonPath('message', $refusal);

        // At 1.08: the tea's 3.50 is EUR 3.24.
        $this->business->exchangeRates()->create(['currency' => 'EUR', 'base_currency' => 'USD', 'rate' => '1.08', 'effective_date' => '2026-11-01']);
        $quote = $this->quote([[$this->tea, '2']])->assertOk()->json('data');
        $this->assertSame(['3.24', '6.48'], [$quote['lines'][0]['price'], $quote['grand_total']]);
    }

    public function test_the_cart_takes_only_what_is_sold_for_one_of_the_businesss_customers(): void
    {
        // No session of one's own open.
        $this->quote([[$this->tea, '1']])->assertStatus(409)->assertJsonPath('message', 'Open your session first: you sell in a session of your own, at a register.');
        $this->getJson(self::SELL.'/items?search=tea')->assertStatus(409);
        $this->openSession();

        $this->quote([[$this->tea, '1'], [$this->oldMug, '1']])->assertUnprocessable()->assertJsonValidationErrors(['lines.1.item_id' => 'Old Mug is no longer sold: take it out of the sale.']);
        $this->quote([[$this->chair, '1']])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.item_id' => 'Office Chair is no longer sold: take it out of the sale.']);
        $theirs = $this->businessOf(User::factory()->create())->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Theirs', 'unit' => 'Piece', 'sale_price' => '1.00']);
        $this->quote([[$theirs, '1']])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.item_id' => 'This item is not one of the business\'s.']);

        // A set whose old mug is switched off is not sold.
        $set = $this->item('Tea Set', '10.00', ['type' => 'combo', 'usage' => 'sales', 'combo_pricing' => 'fixed', 'combo_display' => 'combo_only']);
        $set->components()->createMany([['component_id' => $this->tea->id, 'quantity' => '2', 'sort_order' => 1], ['component_id' => $this->oldMug->id, 'quantity' => '1', 'sort_order' => 2]]);
        $this->quote([[$set, '1']])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.item_id' => 'Tea Set has a component that is switched off, so it is not sold.']);

        // Quantities above 0, to 3 decimals.
        $this->quote([[$this->tea, '0']])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.quantity' => 'The quantity must be more than 0.']);
        $this->quote([[$this->tea, '1.2345']])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.quantity' => 'The quantity takes up to 3 decimals.']);
        $this->quote([[$this->tea, '0.125']])->assertOk()->assertJsonPath('data.grand_total', '0.44');

        // A supplier is no customer.
        $supplier = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Tea Estate']);
        $this->quote([[$this->tea, '1']], $supplier)->assertUnprocessable()->assertJsonValidationErrors(['contact_id' => 'Choose one of the business\'s active customers.']);
    }

    public function test_selling_is_for_those_who_may_open_pos_in_their_own_session(): void
    {
        // Open POS brings Open and Close Sessions, and the contacts a customer is chosen from.
        $this->assertEqualsCanonicalizing(['contacts.view', 'pos.sessions.view', 'pos.sessions.open', 'pos.selling.open'], Permissions::expand(['pos.selling.open']));

        $seller = $this->member(['pos.selling.open']);
        $viewer = $this->member(['pos.sessions.open']);
        $back   = $this->addRegister('Back Counter', 'R2', [$seller->id]);
        $this->openSession();

        // The owner's session is not the seller's.
        Sanctum::actingAs($seller);
        $this->getJson(self::SELL)->assertOk()->assertExactJson(['data' => ['session' => null]]);
        $session = $this->openSession($back);
        $this->assertSame([$session, 'Back Counter'], [$this->getJson(self::SELL)->json('data.session.id'), $this->getJson(self::SELL)->json('data.register.name')]);
        $this->quote([[$this->tea, '1']])->assertOk()->assertJsonPath('data.grand_total', '3.50');

        // No longer at Dhaka: no session to sell in.
        $this->business->members()->where('user_id', $seller->id)->update(['location_access' => WorkspaceMember::SELECTED_LOCATIONS]);
        $this->business->members()->where('user_id', $seller->id)->first()->locations()->sync([$this->chattogram->id]);
        $this->getJson(self::SELL)->assertOk()->assertExactJson(['data' => ['session' => null]]);

        // Sessions alone do not open POS.
        Sanctum::actingAs($viewer);
        $this->getJson(self::SELL)->assertForbidden();
        $this->quote([[$this->tea, '1']])->assertForbidden();
    }
}
