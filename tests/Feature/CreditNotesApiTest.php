<?php

namespace Tests\Feature;

use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Credit notes and refunds (Doc 08 sections 15 and 16): made from an issued
 * invoice's lines, reversing their tax, never crediting more than is left;
 * settling an unpaid invoice, or left available on a paid one to be
 * refunded; voided only before anything was refunded.
 *
 * The base invoice is 2 chairs (270.00 + VAT 40.50) and delivery (25.00):
 * 335.50. One chair back is 135.00 + VAT 20.25 = 155.25.
 */
class CreditNotesApiTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();
    }

    /** A credit note's fields: lines are [invoice line id, quantity, price]. */
    private function note(array $lines, array $fields = []): array
    {
        return [
            'document_date' => '2026-10-28',
            'reason'        => 'Returned',
            'lines'         => array_map(fn (array $line) => ['source_line_id' => $line[0], 'quantity' => $line[1], 'unit_price' => $line[2]], $lines),
            ...$fields,
        ];
    }

    /** A credit note of invoice $id saved and issued - its id. */
    private function issuedNote(int $id, array $lines): int
    {
        $note = $this->postJson("/api/v1/sales/invoices/{$id}/credit-notes", $this->note($lines))->assertCreated()->json('data.id');

        $this->postJson("/api/v1/sales/credit-notes/{$note}/issue")->assertOk();

        return $note;
    }

    private function refund(int $note, string $amount): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/v1/sales/credit-notes/{$note}/refunds", [
            'payment_date'       => '2026-10-28',
            'payment_account_id' => $this->businessOf($this->user)->paymentAccounts()->value('id'),
            'amount'             => $amount,
        ]);
    }

    public function test_the_form_starts_from_the_invoice_lines_with_what_is_left(): void
    {
        $id   = $this->issuedInvoice();
        $data = $this->getJson("/api/v1/sales/invoices/{$id}/credit-note-defaults")->assertOk()->json('data');

        $this->assertSame(['INV-00001', '335.50', '0.00', '2026-10-28'], [$data['invoice']['number'], $data['invoice']['creditable'], $data['invoice']['cash'], $data['document_date']]);
        $this->assertSame(['270.00', '2.000', '150.00'], [$data['lines'][0]['remaining_amount'], $data['lines'][0]['remaining_quantity'], $data['lines'][0]['unit_price']]);
        $this->assertSame(['name' => 'VAT', 'rate' => '15.0000', 'treatment' => 'standard'], $data['lines'][0]['tax']);
        $this->assertNull($data['lines'][1]['tax']);

        $this->getJson('/api/v1/sales/invoices/'.$this->draft('invoices').'/credit-note-defaults')->assertNotFound();
    }

    public function test_a_credit_note_reverses_its_lines_tax_and_settles_an_unpaid_invoice(): void
    {
        $id    = $this->issuedInvoice();
        $chair = $this->lineOf($id, 0);

        $this->postJson("/api/v1/sales/invoices/{$id}/credit-notes/calculate", ['lines' => [['source_line_id' => $chair, 'quantity' => '1', 'unit_price' => '150']]])
            ->assertOk()
            ->assertJsonPath('data.subtotal', '135.00')
            ->assertJsonPath('data.tax_total', '20.25')
            ->assertJsonPath('data.grand_total', '155.25');

        $draft = $this->postJson("/api/v1/sales/invoices/{$id}/credit-notes", $this->note([[$chair, '1', '150']], ['reason' => 'One chair returned']))
            ->assertCreated()
            ->assertJsonPath('message', 'Draft credit note saved.')
            ->json('data');

        $this->assertSame(['credit_note', 'draft', null, 'One chair returned', 'INV-00001', '155.25', 'VAT', ['edit', 'issue', 'delete']],
            [$draft['type'], $draft['status'], $draft['number'], $draft['reason'], $draft['source']['number'], $draft['grand_total'], $draft['lines'][0]['tax']['name'], $draft['actions']]);

        $this->postJson("/api/v1/sales/credit-notes/{$draft['id']}/issue")
            ->assertOk()
            ->assertJsonPath('message', 'Credit Note CN-00001 issued.')
            ->assertJsonPath('data.payment_status', 'fully_applied')
            ->assertJsonPath('data.applied_amount', '155.25')
            ->assertJsonPath('data.balance_due', '0.00')
            ->assertJsonPath('data.actions', ['void']);

        $invoice = $this->getJson("/api/v1/sales/invoices/{$id}")->json('data');
        $this->assertSame(['155.25', '180.25', 'partially_paid', 'CN-00001'], [$invoice['credited_amount'], $invoice['balance_due'], $invoice['payment_status'], $invoice['credit_notes'][0]['number']]);
        $this->getJson("/api/v1/sales/invoices/{$id}/credit-note-defaults")->assertJsonPath('data.invoice.creditable', '180.25')->assertJsonPath('data.lines.0.remaining_amount', '135.00');

        // Nothing is credited twice: one chair is left.
        $this->postJson("/api/v1/sales/invoices/{$id}/credit-notes", $this->note([[$chair, '2', '150']]))
            ->assertJsonValidationErrors(['lines.0.quantity' => 'Only USD 135.00 of this line is left to credit.']);
    }

    public function test_a_credit_note_on_a_paid_invoice_is_refunded_and_voided_only_once_nothing_is_refunded(): void
    {
        $id   = $this->issuedInvoice();
        $cash = $this->account();
        $this->pay($id, $cash, '335.50')->assertCreated();

        $note = $this->issuedNote($id, [[$this->lineOf($id, 1), '1', '25']]);

        $this->getJson("/api/v1/sales/credit-notes/{$note}")
            ->assertJsonPath('data.payment_status', 'available')
            ->assertJsonPath('data.balance_due', '25.00')
            ->assertJsonPath('data.refundable', '25.00')
            ->assertJsonPath('data.actions', ['refund', 'void']);
        $this->getJson("/api/v1/sales/invoices/{$id}")->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.credited_amount', '25.00');

        $this->refund($note, '30')->assertJsonValidationErrors(['amount' => 'At most USD 25.00 can be refunded from this credit note.']);
        $refund = $this->refund($note, '25')->assertCreated()->assertJsonPath('message', 'Refund recorded.')->assertJsonPath('data.kind', 'refund')->json('data.id');

        $this->getJson("/api/v1/sales/credit-notes/{$note}")
            ->assertJsonPath('data.payment_status', 'refunded')
            ->assertJsonPath('data.balance_due', '0.00')
            ->assertJsonPath('data.actions', [])
            ->assertJsonPath('data.payments.0.kind', 'refund');
        $this->getJson("/api/v1/sales/invoices/{$id}")->assertJsonPath('data.refunded_amount', '25.00')->assertJsonPath('data.payment_status', 'paid');
        $this->getJson("/api/v1/payment-accounts/{$cash->id}")->assertJsonPath('data.balance', '310.50');
        $this->getJson("/api/v1/payment-accounts/{$cash->id}/transactions")->assertJsonPath('data.0.type', 'customer_refund')->assertJsonPath('data.0.direction', 'out');

        $this->postJson("/api/v1/sales/credit-notes/{$note}/void")
            ->assertConflict()
            ->assertJsonPath('message', 'Money was refunded out of this credit note. Reverse those refunds before voiding it.');

        $this->postJson("/api/v1/sales/payments/{$refund}/reverse")->assertOk()->assertJsonPath('message', 'Refund reversed.');
        $this->getJson("/api/v1/sales/credit-notes/{$note}")->assertJsonPath('data.balance_due', '25.00');

        $this->postJson("/api/v1/sales/credit-notes/{$note}/void")->assertOk()->assertJsonPath('message', 'Credit Note CN-00001 voided.');
        $this->getJson("/api/v1/sales/invoices/{$id}")->assertJsonPath('data.credited_amount', '0.00')->assertJsonPath('data.balance_due', '0.00');
    }

    public function test_a_whole_invoice_credited_and_refunded_reads_refunded(): void
    {
        $id = $this->issuedInvoice();
        $this->pay($id, $this->account(), '335.50')->assertCreated();

        $note = $this->issuedNote($id, [[$this->lineOf($id, 0), '2', '150'], [$this->lineOf($id, 1), '1', '25']]);

        $this->getJson("/api/v1/sales/credit-notes/{$note}")->assertJsonPath('data.grand_total', '335.50')->assertJsonPath('data.balance_due', '335.50');
        $this->refund($note, '335.50')->assertCreated();

        $this->getJson("/api/v1/sales/invoices/{$id}")
            ->assertJsonPath('data.payment_status', 'refunded')
            ->assertJsonPath('data.actions', []);
        $this->getJson("/api/v1/sales/invoices/{$id}/credit-note-defaults")->assertJsonPath('data.invoice.creditable', '0.00');
    }

    public function test_voiding_a_credit_note_restores_what_the_invoice_owes(): void
    {
        $id   = $this->issuedInvoice();
        $note = $this->issuedNote($id, [[$this->lineOf($id, 1), '1', '25']]);

        $this->getJson("/api/v1/sales/invoices/{$id}")->assertJsonPath('data.balance_due', '310.50');
        $this->postJson("/api/v1/sales/invoices/{$id}/void")->assertConflict();

        $this->postJson("/api/v1/sales/credit-notes/{$note}/void", ['reason' => 'Made in error'])->assertOk();
        $this->getJson("/api/v1/sales/invoices/{$id}")->assertJsonPath('data.balance_due', '335.50')->assertJsonPath('data.payment_status', 'unpaid');

        $this->postJson("/api/v1/sales/invoices/{$id}/void")->assertOk();
    }

    public function test_only_a_draft_credit_note_is_changed_or_deleted_and_each_is_checked_again_on_issue(): void
    {
        $id       = $this->issuedInvoice();
        $delivery = $this->lineOf($id, 1);
        $note     = $this->postJson("/api/v1/sales/invoices/{$id}/credit-notes", $this->note([[$delivery, '1', '25']]))->json('data.id');
        $twin     = $this->postJson("/api/v1/sales/invoices/{$id}/credit-notes", $this->note([[$delivery, '1', '25']]))->json('data.id');

        $this->putJson("/api/v1/sales/credit-notes/{$note}", $this->note([[$delivery, '1', '10']], ['reason' => 'Price corrected']))
            ->assertOk()
            ->assertJsonPath('message', 'Draft credit note updated.')
            ->assertJsonPath('data.grand_total', '10.00');

        $this->postJson("/api/v1/sales/credit-notes/{$note}/issue")->assertOk();

        $this->putJson("/api/v1/sales/credit-notes/{$note}", $this->note([[$delivery, '1', '5']]))
            ->assertConflict()
            ->assertJsonPath('message', 'An issued credit note cannot be changed. Void it and make a new one instead.');
        $this->deleteJson("/api/v1/sales/credit-notes/{$note}")
            ->assertConflict()
            ->assertJsonPath('message', 'Only a draft can be deleted. Void an issued credit note instead.');

        // The twin was saved before the first was issued: only 15.00 of the line is left now.
        $this->postJson("/api/v1/sales/credit-notes/{$twin}/issue")->assertJsonValidationErrors(['lines.0.quantity' => 'Only USD 15.00 of this line is left to credit.']);
        $this->deleteJson("/api/v1/sales/credit-notes/{$twin}")->assertOk();

        $this->postJson("/api/v1/sales/invoices/{$id}/credit-notes", $this->note([[$delivery, '1', '30']]))->assertJsonValidationErrors(['lines.0.unit_price' => 'More than it was invoiced at: 25.00.']);
        $this->postJson("/api/v1/sales/invoices/{$id}/credit-notes", $this->note([[$delivery, '1', '5']], ['document_date' => '2026-10-27']))->assertJsonValidationErrors(['document_date']);
        $this->postJson("/api/v1/sales/invoices/{$id}/credit-notes", $this->note([[$this->lineOf($this->issuedInvoice(), 1), '1', '5']]))->assertJsonValidationErrors(['lines.0.source_line_id' => 'This line is not on the invoice.']);
        $this->postJson('/api/v1/sales/credit-notes', $this->note([[$delivery, '1', '5']]))->assertMethodNotAllowed();
    }

    public function test_the_list_shows_each_credit_note_with_its_invoice(): void
    {
        $id = $this->issuedInvoice();
        $this->pay($id, $this->account(), '335.50')->assertCreated();
        $this->issuedNote($id, [[$this->lineOf($id, 1), '1', '25']]);

        $this->getJson('/api/v1/sales/credit-notes')
            ->assertOk()
            ->assertJsonPath('data.0.number', 'CN-00001')
            ->assertJsonPath('data.0.invoice.number', 'INV-00001')
            ->assertJsonPath('data.0.payment_status', 'available');

        $this->getJson('/api/v1/sales/credit-notes?payment_status=refunded')->assertJsonPath('data', []);
        $this->getJson('/api/v1/sales/invoices')->assertJsonMissingPath('data.0.invoice');
    }
}
