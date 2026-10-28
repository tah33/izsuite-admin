<?php

namespace Tests\Feature;

use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Documents & Templates (Doc 06 sections 13 to 15, Doc 11 section 20): a
 * sales document's and a receipt's PDF, made by Gotenberg - faked here - from
 * the template the document kept when it was numbered; the templates
 * themselves, their preview and signature; and Settings > Email.
 */
class SalesDocumentPdfTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    /** Whether the faked Gotenberg is away. */
    private bool $gotenbergDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();

        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => fn () => $this->gotenbergDown
            ? Http::response('down', 503)
            : Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
    }

    public function test_a_document_pdf_is_drawn_from_it_and_named_after_it(): void
    {
        $id = $this->issuedInvoice();

        $response = $this->get("/api/v1/sales/invoices/{$id}/pdf", ['Accept' => 'application/json'])->assertOk();

        $this->assertSame('%PDF-1.7 fake', $response->getContent());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('inline; filename=Invoice-INV-00001.pdf', $response->headers->get('Content-Disposition'));

        $html = $this->sentHtml();
        $this->assertStringContainsString('INV-00001', $html);
        $this->assertStringContainsString('Uddin Traders', $html);
        $this->assertStringContainsString('Office Chair', $html);
        $this->assertStringContainsString('335.50', $html);
        $this->assertStringNotContainsString('class="stamp', $html);

        $this->get("/api/v1/sales/invoices/{$id}/pdf?download=1", ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=Invoice-INV-00001.pdf');
    }

    public function test_a_draft_says_so_and_another_business_s_document_is_not_found(): void
    {
        $id    = $this->draft('invoices');
        $other = $this->user->workspaces()->create(['name' => 'Second Business']);

        $this->get("/api/v1/sales/invoices/{$id}/pdf", ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('Content-Disposition', "inline; filename=Invoice-draft-{$id}.pdf");
        $this->assertStringContainsString('class="stamp stamp--draft"', $this->sentHtml());

        $this->getJson("/api/v1/sales/invoices/{$id}/pdf", $this->pick($other))->assertNotFound();
        $this->getJson("/api/v1/sales/quotations/{$id}/pdf")->assertNotFound();
    }

    public function test_the_page_is_told_when_pdfs_cannot_be_made(): void
    {
        $this->gotenbergDown = true;

        $this->getJson('/api/v1/sales/invoices/'.$this->issuedInvoice().'/pdf')
            ->assertStatus(503)
            ->assertJsonPath('message', 'PDFs cannot be made right now. Please try again in a moment.');
    }

    public function test_a_receipt_has_a_pdf_and_a_refund_has_none(): void
    {
        $id      = $this->issuedInvoice();
        $cash    = $this->account();
        $payment = $this->pay($id, $cash, '335.50')->assertCreated()->json('data.id');

        $this->get("/api/v1/sales/payments/{$payment}/pdf", ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename=Receipt-RCT-00001.pdf');

        $html = $this->sentHtml();
        $this->assertStringContainsString('RCT-00001', $html);
        $this->assertStringContainsString('INV-00001', $html);

        // Paid in full, so the credit note's 25.00 is refunded rather than set against what is owed.
        $note = $this->postJson("/api/v1/sales/invoices/{$id}/credit-notes", [
            'document_date' => '2026-10-28', 'reason' => 'Returned', 'lines' => [['source_line_id' => $this->lineOf($id, 1), 'quantity' => '1', 'unit_price' => '25.00']],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/credit-notes/{$note}/issue")->assertOk();
        $refund = $this->postJson("/api/v1/sales/credit-notes/{$note}/refunds", [
            'payment_date' => '2026-10-28', 'payment_account_id' => $cash->id, 'amount' => '25.00', 'method' => 'cash',
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/v1/sales/payments/{$refund}/pdf")->assertNotFound()->assertJsonPath('message', 'Only a payment has a receipt.');
    }

    public function test_a_numbered_document_keeps_its_template_and_a_draft_takes_the_new_one(): void
    {
        $issued = $this->issuedInvoice();

        $this->putJson('/api/v1/business-settings/templates/invoice', $this->template(['color' => '#0f766e', 'line_columns' => ['quantity', 'unit_price']]))
            ->assertOk()
            ->assertJsonPath('message', 'Template saved successfully.')
            ->assertJsonPath('data.color', '#0f766e');

        $this->get("/api/v1/sales/invoices/{$issued}/pdf", ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('--accent: #1d4ed8', $this->sentHtml());

        $draft = $this->draft('invoices');
        $this->get("/api/v1/sales/invoices/{$draft}/pdf", ['Accept' => 'application/json'])->assertOk();
        $html = $this->sentHtml();
        $this->assertStringContainsString('--accent: #0f766e', $html);
        $this->assertStringNotContainsString('>Discount<', $html);
    }

    public function test_templates_are_listed_saved_checked_and_previewed(): void
    {
        $templates = $this->getJson('/api/v1/business-settings/templates')->assertOk()->json('data');

        $this->assertSame(['quotation', 'proforma', 'invoice', 'credit_note', 'customer_receipt'], array_column($templates, 'type'));
        $this->assertSame(['#1d4ed8', 'a4', 'logo_left', null], [$templates[2]['color'], $templates[2]['paper_size'], $templates[2]['header_layout'], $templates[2]['signature']]);

        $this->putJson('/api/v1/business-settings/templates/invoice', $this->template(['color' => 'blue', 'visible_fields' => ['totals'], 'paper_size' => 'a3']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['color', 'visible_fields.0', 'paper_size']);
        $this->putJson('/api/v1/business-settings/templates/purchase_order', $this->template())->assertNotFound();

        $response = $this->post('/api/v1/business-settings/templates/customer_receipt/preview', $this->template(['color' => '#7c3aed', 'date_format' => 'd/m/Y']), ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        $html = $this->sentHtml();
        $this->assertStringContainsString('--accent: #7c3aed', $html);
        $this->assertStringContainsString('28/10/2026', $html);
        $this->assertStringContainsString('Sample Customer Ltd.', $html);
        $this->getJson('/api/v1/business-settings/templates')->assertJsonPath('data.4.color', '#1d4ed8');
    }

    public function test_a_template_takes_a_signature_image_and_gives_it_up(): void
    {
        Storage::fake('local');

        $this->post('/api/v1/business-settings/templates/invoice/signature', ['signature' => UploadedFile::fake()->image('sign.png', 300, 100)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message', 'Signature saved.')
            ->assertJsonPath('data.signature.name', 'sign.png')
            ->assertJsonPath('data.color', '#1d4ed8');

        $this->get('/api/v1/sales/invoices/'.$this->draft('invoices').'/pdf', ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('<img src="data:image/png;base64,', $this->sentHtml());

        $this->post('/api/v1/business-settings/templates/invoice/signature', ['signature' => UploadedFile::fake()->create('sign.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.signature.0', 'The signature must be a JPG, PNG or WebP picture.');

        $this->deleteJson('/api/v1/business-settings/templates/invoice/signature')->assertOk()->assertJsonPath('data.signature', null);
        $this->deleteJson('/api/v1/business-settings/templates/invoice/signature')->assertNotFound();
    }

    public function test_email_settings_default_to_the_business_and_its_owner(): void
    {
        $this->getJson('/api/v1/business-settings/email')
            ->assertOk()
            ->assertJsonPath('data.sender_name', null)
            ->assertJsonPath('data.default_sender_name', 'Main Business')
            ->assertJsonPath('data.default_reply_to', $this->user->email)
            ->assertJsonPath('data.templates.invoice.subject', 'Invoice {document_number} from {business_name}');

        $templates = $this->getJson('/api/v1/business-settings/email')->json('data.templates');
        $templates['reminder']['subject'] = 'Friendly reminder: {document_number}';

        $this->putJson('/api/v1/business-settings/email', ['sender_name' => 'Uddin Billing', 'reply_to' => 'billing@main.test', 'language' => 'en', 'templates' => $templates])
            ->assertOk()
            ->assertJsonPath('message', 'Email settings saved successfully.')
            ->assertJsonPath('data.sender_name', 'Uddin Billing')
            ->assertJsonPath('data.templates.reminder.subject', 'Friendly reminder: {document_number}');

        $templates['invoice']['subject'] = "Two\nlines";
        $this->putJson('/api/v1/business-settings/email', ['sender_name' => 'A <b>', 'reply_to' => 'not-an-email', 'language' => 'fr', 'templates' => $templates])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sender_name', 'reply_to', 'language', 'templates.invoice.subject']);
    }

    /** A whole template, as Settings > Documents sends it, with $fields changed. */
    private function template(array $fields = []): array
    {
        return [
            'color' => '#1d4ed8', 'font' => 'sans', 'paper_size' => 'a4', 'header_layout' => 'logo_left', 'show_logo' => true,
            'visible_fields' => ['business_contact', 'customer_contact', 'references', 'location', 'descriptions', 'warranty', 'components'],
            'line_columns' => ['quantity', 'unit_price', 'discount', 'tax'], 'show_notes' => true, 'show_terms' => true,
            'show_payment_instructions' => true, 'footer_text' => null, 'language' => 'en', 'date_format' => 'd M Y', 'number_format' => 'comma_dot',
            ...$fields,
        ];
    }

    /** The HTML of the last PDF asked of Gotenberg. */
    private function sentHtml(): string
    {
        /** @var Request $request */
        [$request] = Http::recorded()->last();

        return (string) collect($request->data())->firstWhere('filename', 'index.html')['contents'];
    }
}
