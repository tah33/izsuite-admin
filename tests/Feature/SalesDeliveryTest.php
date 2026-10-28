<?php

namespace Tests\Feature;

use App\Exceptions\PdfUnavailable;
use App\Jobs\SendDocumentEmail;
use App\Mail\DocumentMail;
use App\Models\Shared\ActivityLog;
use App\Models\User\DocumentLink;
use App\Models\User\User;
use App\Services\Documents\DocumentMailer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A document reaching its customer (Doc 06 sections 9 and 15, Doc 08 section
 * 20, Doc 11 section 22): emailed with its PDF from the business's templates,
 * a payment reminder, a receipt, and a secure link that opens it without
 * signing in until it is revoked, voided or cancelled. The queue runs at once
 * here, so an email is sent - to Mail's fake - before the answer comes back.
 */
class SalesDeliveryTest extends TestCase
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
        $this->customer->update(['email' => 'accounts@uddin.test']);

        Mail::fake();
        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200)]);
    }

    public function test_an_invoice_is_emailed_from_the_business_template_with_its_pdf(): void
    {
        $id    = $this->issuedInvoice();
        $draft = $this->getJson("/api/v1/sales/invoices/{$id}/email-draft")
            ->assertOk()
            ->assertJsonPath('data.to', 'accounts@uddin.test')
            ->assertJsonPath('data.subject', 'Invoice INV-00001 from Main Business')
            ->assertJsonPath('data.reminder', false)
            ->json('data');

        $this->assertStringContainsString('Dear Uddin Traders,', $draft['body']);
        $this->assertStringContainsString('335.50, due on 27 Nov 2026', $draft['body']);

        $this->postJson("/api/v1/sales/invoices/{$id}/email", [...$draft, 'cc' => ['owner@uddin.test']])
            ->assertOk()
            ->assertJsonPath('message', 'Email on its way.')
            ->assertJsonPath('data.emails.0.kind', 'document')
            ->assertJsonPath('data.emails.0.to', 'accounts@uddin.test')
            ->assertJsonPath('data.emails.0.cc', ['owner@uddin.test'])
            ->assertJsonPath('data.emails.0.status', 'sent')
            ->assertJsonPath('data.emails.0.sent_by', trim("{$this->user->first_name} {$this->user->last_name}"));

        Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->hasTo('accounts@uddin.test')
            && $mail->hasCc('owner@uddin.test')
            && $mail->hasFrom((string) config('mail.from.address'), 'Main Business')
            && $mail->hasReplyTo($this->user->email)
            && $mail->hasSubject('Invoice INV-00001 from Main Business')
            && [$mail->filename, $mail->pdf] === ['Invoice-INV-00001.pdf', '%PDF-1.7 fake']);

        $this->assertContains('Emailed invoice INV-00001 to accounts@uddin.test', ActivityLog::where('user_id', $this->user->id)->pluck('description')->all());
    }

    public function test_emailing_a_draft_quotation_sends_it_and_fills_in_its_number(): void
    {
        Queue::fake([SendDocumentEmail::class]);

        $id    = $this->draft('quotations');
        $draft = $this->getJson("/api/v1/sales/quotations/{$id}/email-draft")
            ->assertOk()
            ->assertJsonPath('data.subject', 'Quotation {document_number} from Main Business')
            ->json('data');

        $this->postJson("/api/v1/sales/quotations/{$id}/email", [...$draft, 'body' => "See {document_number} online: {document_link}"])
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.number', 'QUO-00001')
            ->assertJsonPath('data.delivery', ['email', 'share'])
            ->assertJsonPath('data.emails.0.subject', 'Quotation QUO-00001 from Main Business')
            ->assertJsonPath('data.emails.0.status', 'queued');

        $url = route('documents.public', DocumentLink::value('token'));

        $this->getJson("/api/v1/sales/quotations/{$id}")->assertJsonPath('data.link.url', $url);
        Queue::assertPushed(SendDocumentEmail::class, fn (SendDocumentEmail $job) => $job->body === "See QUO-00001 online: {$url}");
    }

    public function test_a_reminder_is_only_for_an_issued_invoice_that_still_owes(): void
    {
        $id = $this->issuedInvoice();

        $draft = $this->getJson("/api/v1/sales/invoices/{$id}/email-draft?reminder=1")
            ->assertOk()
            ->assertJsonPath('data.subject', 'Payment reminder: invoice INV-00001')
            ->json('data');
        $this->assertStringContainsString('335.50 still to pay', $draft['body']);

        $this->postJson("/api/v1/sales/invoices/{$id}/email", $draft)->assertOk()->assertJsonPath('data.emails.0.kind', 'reminder');
        $this->assertContains('Sent a payment reminder for invoice INV-00001 to accounts@uddin.test', ActivityLog::where('user_id', $this->user->id)->pluck('description')->all());

        $this->pay($id, $this->account(), '335.50')->assertCreated();

        $this->getJson("/api/v1/sales/invoices/{$id}")->assertJsonPath('data.delivery', ['email', 'share']);
        $this->getJson("/api/v1/sales/invoices/{$id}/email-draft?reminder=1")
            ->assertConflict()
            ->assertJsonPath('message', 'Only an issued invoice with something still to pay takes a reminder.');

        $draftInvoice = $this->draft('invoices');
        $this->getJson("/api/v1/sales/invoices/{$draftInvoice}")->assertJsonPath('data.delivery', []);
        $this->postJson("/api/v1/sales/invoices/{$draftInvoice}/email", $draft)->assertConflict();
        $this->postJson("/api/v1/sales/invoices/{$id}/email", [...$draft, 'to' => 'nobody', 'cc' => array_fill(0, 6, 'a@b.test'), 'subject' => "A\nB"])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to', 'cc', 'subject']);
    }

    public function test_an_email_that_cannot_go_says_so_and_one_that_went_is_not_sent_again(): void
    {
        Queue::fake([SendDocumentEmail::class]);

        $id    = $this->issuedInvoice();
        $draft = $this->getJson("/api/v1/sales/invoices/{$id}/email-draft")->json('data');

        $this->postJson("/api/v1/sales/invoices/{$id}/email", $draft)->assertOk()->assertJsonPath('data.emails.0.status', 'queued');

        $job = Queue::pushed(SendDocumentEmail::class)->first();
        $job->failed(new PdfUnavailable('down'));

        $this->getJson("/api/v1/sales/invoices/{$id}")
            ->assertJsonPath('data.emails.0.status', 'failed')
            ->assertJsonPath('data.emails.0.failure', 'The PDF could not be made.');

        $this->postJson("/api/v1/sales/invoices/{$id}/email", $draft)->assertOk();
        $retry = Queue::pushed(SendDocumentEmail::class)->last();

        app(DocumentMailer::class)->deliver($retry->emailId, $retry->body);
        app(DocumentMailer::class)->deliver($retry->emailId, $retry->body);

        Mail::assertSentCount(1);
        $this->getJson("/api/v1/sales/invoices/{$id}")->assertJsonPath('data.emails.0.status', 'sent')->assertJsonPath('data.emails.1.status', 'failed');
    }

    public function test_a_receipt_is_emailed_while_its_payment_stands(): void
    {
        $id      = $this->issuedInvoice();
        $payment = $this->pay($id, $this->account(), '100')->assertCreated()->assertJsonPath('data.delivery', ['email'])->json('data.id');

        $draft = $this->getJson("/api/v1/sales/payments/{$payment}/email-draft")
            ->assertOk()
            ->assertJsonPath('data.to', 'accounts@uddin.test')
            ->assertJsonPath('data.subject', 'Receipt RCT-00001 from Main Business')
            ->json('data');
        $this->assertStringContainsString('your payment of', strtolower($draft['body']));

        $this->postJson("/api/v1/sales/payments/{$payment}/email", $draft)
            ->assertOk()
            ->assertJsonPath('data.emails.0.status', 'sent');
        Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->filename === 'Receipt-RCT-00001.pdf');

        $this->postJson("/api/v1/sales/payments/{$payment}/reverse", ['reason' => 'Cheque bounced'])->assertOk()->assertJsonPath('data.delivery', []);
        $this->postJson("/api/v1/sales/payments/{$payment}/email", $draft)
            ->assertConflict()
            ->assertJsonPath('message', 'This payment was reversed, so its receipt is not sent.');
    }

    public function test_a_secure_link_opens_the_document_until_it_is_revoked(): void
    {
        $id  = $this->issuedInvoice();
        $url = $this->postJson("/api/v1/sales/invoices/{$id}/link")
            ->assertOk()
            ->assertJsonPath('message', 'Secure link ready to share.')
            ->assertJsonPath('data.views', 0)
            ->json('data.url');

        $this->postJson("/api/v1/sales/invoices/{$id}/link")->assertJsonPath('data.url', $url);
        $this->assertSame(1, DocumentLink::count());

        $this->get($url)
            ->assertOk()
            ->assertSee('INV-00001')
            ->assertSee('Download PDF')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get("{$url}/pdf")->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=Invoice-INV-00001.pdf');

        $this->getJson("/api/v1/sales/invoices/{$id}")->assertJsonPath('data.link.url', $url)->assertJsonPath('data.link.views', 1);

        $this->deleteJson("/api/v1/sales/invoices/{$id}/link")->assertOk()->assertJsonPath('message', 'Link revoked. It no longer opens the document.');
        $this->get($url)->assertStatus(410)->assertSee('This document is no longer available');
        $this->get("{$url}/pdf")->assertStatus(410);
        $this->getJson("/api/v1/sales/invoices/{$id}")->assertJsonPath('data.link', null);
        $this->deleteJson("/api/v1/sales/invoices/{$id}/link")->assertNotFound();
    }

    public function test_a_link_needs_a_numbered_document_and_ends_when_it_is_voided_or_cancelled(): void
    {
        $this->postJson('/api/v1/sales/invoices/'.$this->draft('invoices').'/link')
            ->assertConflict()
            ->assertJsonPath('message', 'Only a numbered document that is not void or cancelled can be shared. Send or issue it first.');

        $invoice = $this->issuedInvoice();
        $url     = $this->postJson("/api/v1/sales/invoices/{$invoice}/link")->json('data.url');
        $this->postJson("/api/v1/sales/invoices/{$invoice}/void", ['reason' => 'Ordered twice'])->assertOk()->assertJsonPath('data.delivery', []);
        $this->get($url)->assertStatus(410);

        $quotation = $this->draft('quotations');
        $this->postJson("/api/v1/sales/quotations/{$quotation}/send")->assertOk();
        $url = $this->postJson("/api/v1/sales/quotations/{$quotation}/link")->json('data.url');
        $this->get($url)->assertOk()->assertSee('QUO-00001');
        $this->postJson("/api/v1/sales/quotations/{$quotation}/cancel", ['reason' => 'Lost'])->assertOk();
        $this->get($url)->assertStatus(410);

        $this->assertSame(2, DocumentLink::whereNotNull('revoked_at')->count());
        $this->get('/d/'.str_repeat('a', 48))->assertStatus(410);
    }
}
