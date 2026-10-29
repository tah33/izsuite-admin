<?php

namespace Tests\Feature;

use App\Models\Frontend\MenuItem;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * What Doc 08 asks of Sales Lite beyond its documents' own rules: each list
 * row's actions and location (section 6), an exchange rate chosen on purpose
 * when an invoice is issued (sections 11 and 18), a quotation's tax shown as
 * an estimate (section 17), and Invoices leading its menu (section 2).
 */
class SalesLiteListsAndIssuingTest extends TestCase
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

        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();
    }

    public function test_a_list_row_says_what_it_allows_now(): void
    {
        $draft = $this->draft('invoices');
        $paid  = $this->issuedInvoice();
        $this->pay($paid, $this->account(), '335.50')->assertCreated();

        $note = $this->postJson("/api/v1/sales/invoices/{$paid}/credit-notes", [
            'document_date' => '2026-10-28', 'reason' => 'Returned', 'lines' => [['source_line_id' => $this->lineOf($paid, 1), 'quantity' => '1', 'unit_price' => '25.00']],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/credit-notes/{$note}/issue")->assertOk();

        $rows = collect($this->getJson('/api/v1/sales/invoices')->assertOk()->json('data'))->keyBy('id');

        $this->assertSame([['edit', 'issue', 'delete'], [], '0.00'], [$rows[$draft]['actions'], $rows[$draft]['delivery'], $rows[$draft]['amount_paid']]);
        $this->assertSame(['credit_note', 'refund'], $rows[$paid]['actions']);
        $this->assertSame('335.50', $rows[$paid]['amount_paid']);

        // A credit note's refund is worked out from what its invoice took.
        $this->getJson('/api/v1/sales/credit-notes')->assertJsonPath('data.0.actions', ['refund', 'void']);
    }

    public function test_an_invoice_in_another_currency_is_issued_at_the_rate_chosen(): void
    {
        $business = $this->businessOf($this->user);
        $business->forceFill(['additional_currencies' => ['EUR']])->save();
        $business->exchangeRates()->create(['currency' => 'EUR', 'base_currency' => 'USD', 'rate' => '1.10', 'effective_date' => '2026-10-01']);

        $quoted  = $this->draft('invoices', ['currency' => 'EUR', 'exchange_rate' => '1.05']);
        $current = $this->draft('invoices', ['currency' => 'EUR', 'exchange_rate' => '1.05']);
        $same    = $this->draft('invoices', ['currency' => 'EUR', 'exchange_rate' => '1.10']);

        // A rate that is not the day's is never swapped in, nor kept, without being asked.
        $this->postJson("/api/v1/sales/invoices/{$quoted}/issue")
            ->assertUnprocessable()
            ->assertJsonPath('errors.exchange_rate.0', 'This invoice is at 1 EUR = 1.05 USD, but the rate for 28 Oct 2026 is 1.1 USD. Choose which rate to issue it at.');
        $this->assertSame('draft', SalesDocument::find($quoted)->status->value);

        $this->postJson("/api/v1/sales/invoices/{$quoted}/issue", ['exchange_rate' => 'keep'])->assertOk();
        $this->assertSame(['1.05000000', '352.28'], [(string) SalesDocument::find($quoted)->exchange_rate, (string) SalesDocument::find($quoted)->base_grand_total]);

        $this->postJson("/api/v1/sales/invoices/{$current}/issue", ['exchange_rate' => 'current'])->assertOk();
        $issued = SalesDocument::find($current);
        $this->assertSame(['1.10000000', '369.05', '44.55', '324.50'], [(string) $issued->exchange_rate, (string) $issued->base_grand_total, (string) $issued->base_tax_total, (string) $issued->base_net_total]);

        $this->postJson("/api/v1/sales/invoices/{$same}/issue")->assertOk();
        $this->postJson("/api/v1/sales/invoices/{$this->draft('invoices')}/issue", ['exchange_rate' => 'today'])->assertUnprocessable()->assertJsonValidationErrors('exchange_rate');
    }

    public function test_a_quotation_s_tax_is_an_estimate_on_its_pdf(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);

        $this->get('/api/v1/sales/quotations/'.$this->draft('quotations').'/pdf', ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('VAT 15% (estimated)', $this->sentHtml());

        $this->get('/api/v1/sales/invoices/'.$this->issuedInvoice().'/pdf', ['Accept' => 'application/json'])->assertOk();
        $this->assertStringNotContainsString('(estimated)', $this->sentHtml());
    }

    public function test_invoices_lead_the_sales_lite_menu(): void
    {
        foreach (['/sales/quotations' => 70, '/sales/proformas' => 80, '/sales/invoices' => 90, '/sales/credit-notes' => 100] as $route => $order) {
            MenuItem::create(['section' => 'apps', 'family_key' => 'sales', 'label' => $route, 'icon' => 'receipt', 'route' => $route, 'min_edition' => 'lite', 'sort_order' => $order, 'is_active' => true]);
        }

        (require database_path('migrations/2025_01_01_000092_put_invoices_first_in_sales_lite_menu.php'))->up();

        $this->assertSame(
            ['/sales/invoices', '/sales/quotations', '/sales/proformas', '/sales/credit-notes'],
            MenuItem::where('family_key', 'sales')->orderBy('sort_order')->pluck('route')->all(),
        );
    }

    /** The HTML of the last PDF asked of Gotenberg. */
    private function sentHtml(): string
    {
        /** @var Request $request */
        [$request] = Http::recorded()->last();

        return (string) collect($request->data())->firstWhere('filename', 'index.html')['contents'];
    }
}
