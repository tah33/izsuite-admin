<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\SalesDocument;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase G3: POS's documents on the Documents page - its sales
 * receipts, the formal invoices made of them, and its returns' refund
 * receipts - at Front Counter (R1) in Dhaka, taking Cash into the Shop Till:
 * a mug sold to the walk-in customer, another to Rahman Traders and formally
 * invoiced, the first brought back - and an invoice of Sales Lite's own.
 */
class PosDocumentsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        Application::create(['app_key' => 'pos', 'name' => 'Point of Sale', 'family_key' => 'pos', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 30, 'is_active' => true, 'status' => 'available'])
            ->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-20 10:00:00', 'UTC'));
        $user     = $this->signIn();
        $business = $this->businessOf($user);
        $business->forceFill(['base_currency' => 'USD', 'tax_mode' => 'later'])->save();
        $dhaka = $business->locations()->create(['user_id' => $user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $cash = (int) $this->getJson('/api/v1/pos/registers/options')->assertOk()->json('data.payment_methods.0.id');

        $walkIn = $business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $rahman = $business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Rahman Traders']);
        $business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $walkIn->id]);
        $mug = $business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '15.00']);

        $register = $this->postJson('/api/v1/pos/registers', [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$cash], 'cashier_ids' => [$user->id],
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $register, 'opening_float' => '50'])->assertCreated();

        foreach ([$walkIn, $rahman] as $customer) {
            $this->postJson('/api/v1/pos/orders', [
                'contact_id' => $customer->id, 'lines' => [['item_id' => $mug->id, 'quantity' => '1']], 'total' => '15.00', 'payments' => [['method_id' => $cash, 'amount' => '15.00']],
            ])->assertCreated();
        }

        $this->postJson('/api/v1/pos/orders/2/formal-invoice')->assertOk();
        $line = SalesDocument::where('number', 'R1-00001')->sole()->lines->first()->id;
        $this->postJson('/api/v1/pos/returns', ['order_id' => 1, 'lines' => [['line_id' => $line, 'quantity' => '1', 'condition' => 'resellable']], 'reason' => 'Chipped', 'refund' => 'cash'])->assertCreated();

        // Sales Lite's own invoice, issued.
        $invoice = $this->postJson('/api/v1/sales/invoices', [
            'contact_id' => $rahman->id, 'location_id' => $dhaka->id, 'document_date' => '2026-11-20', 'currency' => 'USD', 'lines' => [['name' => 'Delivery', 'quantity' => '1', 'unit_price' => '5.00']],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/invoices/{$invoice}/issue")->assertOk();
    }

    public function test_pos_documents_say_what_they_are_at_pos(): void
    {
        $rows = collect($this->getJson('/api/v1/documents')->assertOk()->json('data'))->keyBy('number');

        // Each keeps its POS order, register and session.
        $this->assertSame(['invoices', 'sales_receipt', 'Point of Sale', 'Front Counter (R1) · S-00001'], [$rows['R1-00001']['kind'], $rows['R1-00001']['pos'], $rows['R1-00001']['source'], $rows['R1-00001']['pos_reference']]);
        $this->assertSame(['invoices', 'invoice', 'Point of Sale', 'R1-00002 · Front Counter (R1) · S-00001'], [$rows['INV-00001']['kind'], $rows['INV-00001']['pos'], $rows['INV-00001']['source'], $rows['INV-00001']['pos_reference']]);
        $this->assertSame(['credit-notes', 'credit_note', 'Point of Sale', 'RT-00001 · R1-00001 · Front Counter (R1) · S-00001'], [$rows['CN-00001']['kind'], $rows['CN-00001']['pos'], $rows['CN-00001']['source'], $rows['CN-00001']['pos_reference']]);
        $this->assertSame([null, 'Sales Lite', null], [$rows['INV-00002']['pos'], $rows['INV-00002']['source'], $rows['INV-00002']['pos_reference']]);

        // The return's refund receipt, a row of its own.
        $this->assertSame([
            'kind' => 'pos-refund-receipts', 'number' => 'RT-00001', 'document_date' => '2026-11-20', 'contact' => 'Walk-in Customer', 'source' => 'Point of Sale', 'pos' => 'refund_receipt',
            'pos_reference' => 'R1-00001 · Front Counter (R1) · S-00001', 'status' => 'completed', 'grand_total' => '15.00', 'currency' => 'USD', 'balance_due' => null, 'actions' => [],
        ], array_intersect_key($rows['RT-00001'], array_flip(['kind', 'number', 'document_date', 'contact', 'source', 'pos', 'pos_reference', 'status', 'grand_total', 'currency', 'balance_due', 'actions'])));
    }

    public function test_the_point_of_sale_source_lists_pos_documents_alone(): void
    {
        $numbers = fn (string $query) => collect($this->getJson("/api/v1/documents?{$query}")->assertOk()->json('data'))->pluck('number')->sort()->values()->all();

        $this->assertSame(['CN-00001', 'INV-00001', 'R1-00001', 'RT-00001'], $numbers('source=pos'));
        $this->assertSame(['CN-00001', 'INV-00001', 'INV-00002', 'R1-00001'], $numbers('source=sales'));
        $this->assertSame([], $numbers('source=purchases'));
        // A refund receipt is found by its number or its sale's; a sales type leaves it out.
        $found = $numbers('search=R1-00001');
        $this->assertContains('RT-00001', $found);
        $this->assertContains('R1-00001', $found);
        $this->assertNotContains('INV-00002', $found);
        $this->assertSame(['INV-00001', 'INV-00002', 'R1-00001'], $numbers('type=invoice'));
    }
}
