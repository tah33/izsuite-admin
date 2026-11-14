<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\PaymentAccount;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase D2: deposit invoices of a confirmed sales order - a fixed
 * amount or a percentage of its total, paid like any invoice, and deducted by
 * the order's later invoices, never more than each has left; the order never
 * billed past its total without the permission and a reason; a deducted
 * deposit kept from being voided. The order comes to USD 335.50, like the base
 * invoice. Wednesday 28 October 2026.
 */
class SalesDepositsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private PaymentAccount $cash;

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
        $this->cash = $this->account();
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
    }

    private function order(): int
    {
        $id = $this->draft('orders');

        $this->postJson("/api/v1/sales/orders/{$id}/confirm")->assertOk();

        return $id;
    }

    /** The whole order invoiced: a draft invoice's id. */
    private function invoiceAll(int $order): int
    {
        $lines = $this->getJson("/api/v1/sales/orders/{$order}")->json('data.lines');

        return $this->postJson("/api/v1/sales/orders/{$order}/invoice", [
            'lines' => array_map(fn (array $line) => ['line_id' => $line['id'], 'quantity' => $line['quantity']], $lines),
        ])->assertCreated()->json('data.id');
    }

    public function test_a_deposit_is_paid_and_deducted_by_the_orders_next_invoice(): void
    {
        $order = $this->order();

        $this->assertContains('create_deposit', $this->getJson("/api/v1/sales/orders/{$order}")->json('data.actions'));

        $deposit = $this->postJson("/api/v1/sales/orders/{$order}/deposit", ['type' => 'percent', 'value' => '40'])
            ->assertCreated()
            ->assertJsonPath('message', 'Draft deposit invoice made from sales order SO-00001.')
            ->assertJsonPath('data.is_deposit', true)
            ->assertJsonPath('data.grand_total', '134.20')
            ->assertJsonPath('data.tax_total', '0.00')
            ->assertJsonPath('data.lines.0.name', 'Deposit for Sales Order SO-00001')
            ->json('data.id');

        $this->postJson("/api/v1/sales/invoices/{$deposit}/issue")->assertOk()->assertJsonPath('data.number', 'INV-00001');
        $this->pay($deposit, $this->cash, '134.20')->assertCreated();

        // The order's next invoice deducts it, with the order's tax charged in full.
        $final = $this->invoiceAll($order);
        $shown = $this->getJson("/api/v1/sales/invoices/{$final}")->json('data');

        $this->assertSame(['Less deposit INV-00001', '-134.20', $deposit], [$shown['lines'][2]['name'], $shown['lines'][2]['unit_price'], $shown['lines'][2]['deposit_invoice_id']]);
        $this->assertSame(['40.50', '201.30'], [$shown['tax_total'], $shown['grand_total']]);

        $this->postJson("/api/v1/sales/invoices/{$final}/issue")->assertOk();

        $this->assertSame([['INV-00001', '134.20', '134.20']], array_map(fn (array $row) => [$row['number'], $row['grand_total'], $row['deducted']], $this->getJson("/api/v1/sales/orders/{$order}")->json('data.deposits')));
    }

    public function test_a_deposit_deducted_by_another_invoice_is_not_voided(): void
    {
        $order   = $this->order();
        $deposit = $this->postJson("/api/v1/sales/orders/{$order}/deposit", ['type' => 'amount', 'value' => '50'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/invoices/{$deposit}/issue")->assertOk();

        // Unpaid, it could be voided - until a draft of the order deducts it.
        $final = $this->invoiceAll($order);
        $this->postJson("/api/v1/sales/invoices/{$deposit}/void", ['reason' => 'Mistake'])->assertStatus(409)
            ->assertJsonPath('message', 'Later invoices of the order deduct this deposit. Void them, or take the deduction off a draft, first.');

        $this->deleteJson("/api/v1/sales/invoices/{$final}")->assertOk();
        $this->postJson("/api/v1/sales/invoices/{$deposit}/void", ['reason' => 'Mistake'])->assertOk();
        $this->assertSame([['INV-00001', 'void', '0.00']], array_map(fn (array $row) => [$row['number'], $row['status'], $row['deducted']], $this->getJson("/api/v1/sales/orders/{$order}")->json('data.deposits')));
    }

    public function test_a_deposit_past_the_orders_total_needs_a_reason(): void
    {
        $order = $this->order();

        $this->postJson("/api/v1/sales/orders/{$order}/deposit", ['type' => 'percent', 'value' => '101'])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->postJson("/api/v1/sales/orders/{$order}/deposit", ['type' => 'amount', 'value' => '400'])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->postJson("/api/v1/sales/orders/{$order}/deposit", ['type' => 'amount', 'value' => '400', 'reason' => 'Agreed with the customer'])
            ->assertCreated()
            ->assertJsonPath('data.over_invoice_reason', 'Agreed with the customer');

        // A draft order takes none.
        $this->postJson('/api/v1/sales/orders/'.$this->draft('orders').'/deposit', ['type' => 'amount', 'value' => '10'])->assertStatus(409);
    }

    public function test_a_deduction_stays_within_what_the_deposit_has_left(): void
    {
        $order   = $this->order();
        $deposit = $this->postJson("/api/v1/sales/orders/{$order}/deposit", ['type' => 'amount', 'value' => '100'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/invoices/{$deposit}/issue")->assertOk();

        // The chairs first: their invoice deducts all 100 of it, which its form may lower, never raise.
        [$chairs, $delivery] = array_column($this->getJson("/api/v1/sales/orders/{$order}")->json('data.lines'), 'id');
        $final = $this->postJson("/api/v1/sales/orders/{$order}/invoice", ['lines' => [['line_id' => $chairs, 'quantity' => '2']]])->assertCreated()->json('data.id');
        $chair = $this->getJson("/api/v1/sales/invoices/{$final}")->json('data.lines.0');
        $save  = fn (string $deducted) => $this->putJson("/api/v1/sales/invoices/{$final}", $this->invoice([
            'lines' => [
                [...array_intersect_key($chair, array_flip(['source_line_id', 'item_id', 'name', 'quantity', 'unit', 'unit_price', 'discount_type', 'discount_value', 'tax_rate_id']))],
                ['deposit_invoice_id' => $deposit, 'name' => 'Less deposit INV-00001', 'quantity' => '1', 'unit_price' => "-{$deducted}"],
            ],
        ]));

        $save('150.00')->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Only USD 100.00 of deposit INV-00001 is left to deduct.');
        $save('60.00')->assertOk()->assertJsonPath('data.grand_total', '250.50');

        // Issued like that, the order would bill 350.50 of its 335.50: refused without a reason.
        $this->postJson("/api/v1/sales/invoices/{$final}/issue")->assertStatus(409)
            ->assertJsonPath('message', "With its deposits, SO-00001's invoices would bill more than its total. Deduct more of the deposits, or say why in its form - billing beyond the order needs \"Invoice Beyond the Order\".");

        // Only a later invoice of the order deducts; a negative price is otherwise refused.
        $this->postJson('/api/v1/sales/invoices', $this->invoice(['lines' => [
            ['name' => 'Chair', 'quantity' => '1', 'unit_price' => '150'],
            ['deposit_invoice_id' => $deposit, 'name' => 'Less deposit', 'quantity' => '1', 'unit_price' => '-50'],
        ]]))->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->postJson('/api/v1/sales/invoices', $this->invoice(['lines' => [['name' => 'Discount', 'quantity' => '1', 'unit_price' => '-50']]]))
            ->assertUnprocessable()->assertJsonValidationErrors('lines.0.unit_price');

        // What the draft holds is held: the delivery's invoice deducts only from the 40 left, up to its own 25.
        $second = $this->postJson("/api/v1/sales/orders/{$order}/invoice", ['lines' => [['line_id' => $delivery, 'quantity' => '1']]])->assertCreated()->json('data.id');
        $this->assertSame(['-25.00', '0.00'], [
            collect($this->getJson("/api/v1/sales/invoices/{$second}")->json('data.lines'))->firstWhere('deposit_invoice_id', $deposit)['unit_price'],
            $this->getJson("/api/v1/sales/invoices/{$second}")->json('data.grand_total'),
        ]);
    }
}
