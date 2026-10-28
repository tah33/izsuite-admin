<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Sales documents moving on (Doc 08 sections 7 to 12): issuing an invoice,
 * overdue and void; sending, accepting, rejecting and cancelling a quotation,
 * expiry; revisions of a sent quotation; and conversions.
 */
class SalesDocumentFlowTest extends TestCase
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

    public function test_issuing_numbers_an_invoice_keeps_who_it_was_for_and_locks_it(): void
    {
        $id = $this->draft('invoices');

        $data = $this->postJson("/api/v1/sales/invoices/{$id}/issue")
            ->assertOk()
            ->assertJsonPath('message', 'Invoice INV-00001 issued.')
            ->json('data');

        $this->assertSame(['issued', 'INV-00001', 'unpaid', '335.50', ['record_payment', 'credit_note', 'void']], [$data['status'], $data['number'], $data['payment_status'], $data['balance_due'], $data['actions']]);
        $this->assertSame(['Uddin Traders', 'Main Business'], [$data['customer']['name'], $data['business']['name']]);
        // Its country as printed, by name.
        $this->assertSame(['Dhaka', 'Bangladesh'], $data['customer']['billing_address']);

        // The customer's later changes never reach it.
        $this->customer->update(['name' => 'Uddin Traders Ltd']);
        $this->getJson("/api/v1/sales/invoices/{$id}")->assertJsonPath('data.customer.name', 'Uddin Traders');

        $this->putJson("/api/v1/sales/invoices/{$id}", $this->invoice())->assertConflict()->assertJsonPath('message', 'An issued invoice cannot be changed. Correct it with a credit note.');
        $this->deleteJson("/api/v1/sales/invoices/{$id}")->assertConflict()->assertJsonPath('message', 'Only a draft can be deleted. Void an issued invoice instead.');
        $this->postJson("/api/v1/sales/invoices/{$id}/issue")->assertConflict();

        $this->postJson('/api/v1/sales/invoices/'.$this->draft('invoices').'/issue')->assertJsonPath('data.number', 'INV-00002');
        $this->assertContains('Issued invoice INV-00001', ActivityLog::where('user_id', $this->user->id)->pluck('description')->all());
    }

    public function test_an_issued_invoice_past_its_due_date_is_overdue_until_it_is_voided(): void
    {
        $id = $this->draft('invoices', ['due_date' => '2026-10-29']);
        $this->postJson("/api/v1/sales/invoices/{$id}/issue")->assertJsonPath('data.overdue', false);

        $this->travelTo(Carbon::parse('2026-10-30 10:00:00', 'UTC'));

        $this->getJson("/api/v1/sales/invoices/{$id}")->assertJsonPath('data.overdue', true);
        $this->assertSame([$id], array_column($this->getJson('/api/v1/sales/invoices?overdue=1')->json('data'), 'id'));

        $this->postJson("/api/v1/sales/invoices/{$id}/void", ['reason' => 'Ordered twice'])
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.balance_due', '0.00')
            ->assertJsonPath('data.payment_status', 'not_applicable')
            ->assertJsonPath('data.overdue', false)
            ->assertJsonPath('data.status_reason', 'Ordered twice');

        $this->assertSame([], $this->getJson('/api/v1/sales/invoices?overdue=1')->json('data'));
    }

    public function test_a_quotation_is_sent_and_answered_but_not_once_it_has_expired(): void
    {
        $id = $this->draft('quotations', ['expiry_date' => '2026-11-05']);

        $this->postJson("/api/v1/sales/quotations/{$id}/send")
            ->assertOk()
            ->assertJsonPath('message', 'Quotation QUO-00001 marked as sent.')
            ->assertJsonPath('data.actions', ['edit', 'send', 'accept', 'reject', 'convert_to_proforma', 'convert_to_invoice', 'cancel']);

        $this->postJson("/api/v1/sales/quotations/{$id}/send")->assertJsonPath('data.number', 'QUO-00001');
        $this->postJson("/api/v1/sales/quotations/{$id}/accept")->assertJsonPath('data.status', 'accepted');
        $this->postJson("/api/v1/sales/quotations/{$id}/reject")->assertConflict();

        $late = $this->draft('quotations', ['expiry_date' => '2026-11-05']);
        $this->postJson("/api/v1/sales/quotations/{$late}/send")->assertJsonPath('data.number', 'QUO-00002');

        $this->travelTo(Carbon::parse('2026-11-30 10:00:00', 'UTC'));

        $this->getJson("/api/v1/sales/quotations/{$late}")->assertJsonPath('data.status', 'expired');
        $this->postJson("/api/v1/sales/quotations/{$late}/accept")->assertConflict()->assertJsonPath('message', 'Only a sent quotation that has not expired can be accepted.');
        $this->postJson("/api/v1/sales/quotations/{$late}/cancel", ['reason' => 'No answer'])->assertJsonPath('data.status', 'cancelled');

        $this->postJson("/api/v1/sales/invoices/{$id}/issue")->assertNotFound();
    }

    public function test_changing_a_sent_quotation_keeps_the_version_before(): void
    {
        $id = $this->draft('quotations');
        $this->postJson("/api/v1/sales/quotations/{$id}/send");

        $changed = $this->quotation(['lines' => [[...$this->invoice()['lines'][0], 'quantity' => '3']]]);

        $this->putJson("/api/v1/sales/quotations/{$id}", $changed)->assertOk()->assertJsonPath('data.revision', 1)->assertJsonPath('data.status', 'sent');

        // Saving it again unchanged makes no new revision.
        $this->putJson("/api/v1/sales/quotations/{$id}", $changed)->assertJsonPath('data.revision', 1);

        $this->assertSame([[0, $this->user->name]], array_map(fn (array $row) => [$row['revision'], $row['changed_by']], $this->getJson("/api/v1/sales/quotations/{$id}/revisions")->json('data')));

        $before = $this->getJson("/api/v1/sales/quotations/{$id}/revisions/0")->assertOk()->json('data.snapshot');
        $this->assertSame(['QUO-00001', '2.000', '335.50'], [$before['number'], $before['lines'][0]['quantity'], $before['grand_total']]);

        $this->assertContains('Updated quotation QUO-00001 - now revision 1', ActivityLog::where('user_id', $this->user->id)->pluck('description')->all());
    }

    public function test_a_quotation_becomes_an_invoice_once_unless_asked_again(): void
    {
        $id = $this->draft('quotations');
        $this->postJson("/api/v1/sales/quotations/{$id}/send");

        $invoice = $this->postJson("/api/v1/sales/quotations/{$id}/convert", ['to' => 'invoice'])
            ->assertCreated()
            ->assertJsonPath('message', 'Draft invoice made from quotation QUO-00001.')
            ->json('data');

        $this->assertSame(['invoice', 'draft', '335.50', '2026-11-27', 'QUO-00001'], [$invoice['type'], $invoice['status'], $invoice['grand_total'], $invoice['due_date'], $invoice['source']['number']]);
        $this->assertCount(2, $invoice['lines']);

        $this->getJson("/api/v1/sales/quotations/{$id}")
            ->assertJsonPath('data.status', 'converted')
            ->assertJsonPath('data.conversions.0.id', $invoice['id']);

        $this->postJson("/api/v1/sales/quotations/{$id}/convert", ['to' => 'invoice'])->assertConflict();
        $this->postJson("/api/v1/sales/quotations/{$id}/convert", ['to' => 'proforma', 'again' => true])->assertCreated()->assertJsonPath('data.expiry_date', '2026-11-27');

        $proforma = $this->draft('proformas');
        $this->postJson("/api/v1/sales/proformas/{$proforma}/convert", ['to' => 'proforma'])->assertConflict()->assertJsonPath('message', 'This proforma invoice cannot become a proforma invoice.');
        $this->postJson("/api/v1/sales/proformas/{$proforma}/convert", ['to' => 'invoice'])->assertCreated()->assertJsonPath('message', 'Draft invoice made from the draft proforma invoice.');
    }

    public function test_a_tax_rate_switched_off_since_is_chosen_again_before_issuing(): void
    {
        $id = $this->draft('invoices');

        $this->vat->update(['is_active' => false]);

        $this->postJson("/api/v1/sales/invoices/{$id}/issue")
            ->assertConflict()
            ->assertJsonPath('message', 'VAT on "Office Chair" is no longer in effect on 2026-10-28. Edit it and choose the current rate first.');
    }
}
