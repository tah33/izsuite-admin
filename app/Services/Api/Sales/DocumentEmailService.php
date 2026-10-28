<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Jobs\SendDocumentEmail;
use App\Models\User\DocumentEmail;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\DocumentEmailRepository;
use App\Repositories\User\DocumentLinkRepository;
use App\Repositories\User\EmailSettingRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\SalesPaymentRepository;
use App\Services\Documents\DocumentEmailText;
use App\Services\Documents\DocumentLinks;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Model;

/**
 * Emailing a sales document or a payment receipt to the customer with its
 * PDF attached (Doc 06 section 15, Doc 08 section 20), and the payment
 * reminder for an invoice that still owes (Doc 11 section 22).
 *
 * The form starts from the business's email for that kind (Settings >
 * Email) with its placeholders filled in, and what goes out is what the form
 * says. Emailing a draft quotation or proforma sends it: it is numbered and
 * marked sent first. {document_link} makes the document's secure link if it
 * has none. The email goes out from the queue; its row records whether the
 * mail server took it.
 */
class DocumentEmailService
{
    use RefusesConflicts;

    public function __construct(
        protected SalesDocumentService $salesDocumentService,
        protected SalesDocumentRules $salesDocumentRules,
        protected SalesDocumentFlow $salesDocumentFlow,
        protected SalesDocumentRepository $salesDocumentRepository,
        protected SalesPdfService $salesPdfService,
        protected SalesPaymentRepository $salesPaymentRepository,
        protected EmailSettingRepository $emailSettingRepository,
        protected DocumentEmailRepository $documentEmailRepository,
        protected DocumentLinkRepository $documentLinkRepository,
        protected DocumentLinks $documentLinks,
        protected DocumentEmailText $documentEmailText,
    ) {}

    /** @return array{to: ?string, subject: string, body: string, reminder: bool} what the email form starts with */
    public function documentDraft(Workspace $workspace, DocumentType $type, int $id, bool $reminder): array
    {
        $document = $this->deliverable($workspace, $type, $id, $reminder);
        $link     = $this->documentLinkRepository->activeFor($document);
        $values   = $this->documentEmailText->forDocument($workspace, $document, $link ? $this->documentLinks->url($link) : null);

        return $this->draft($workspace, $reminder ? 'reminder' : $type->value, $values, $this->documentEmailText->customer($document)['email'] ?? null, $reminder);
    }

    /** $data is validated (see SendDocumentEmailRequest). */
    public function sendDocument(Workspace $workspace, DocumentType $type, int $id, User $user, array $data): SalesDocument
    {
        $reminder = (bool) ($data['reminder'] ?? false);
        $document = $this->deliverable($workspace, $type, $id, $reminder);

        if ($document->isDraft()) {
            $document = $this->salesDocumentFlow->send($workspace, $type, $id, $user);
        }

        $link = str_contains($data['subject'].$data['body'], '{document_link}')
            ? $this->documentLinks->ensure($workspace, $document, $user)
            : null;

        $this->queue($workspace, $document, $user, $reminder ? DocumentEmail::REMINDER : DocumentEmail::DOCUMENT, $data,
            $this->documentEmailText->forDocument($workspace, $document, $link ? $this->documentLinks->url($link) : ''));

        ActivityLogService::record('updated', ($reminder ? 'Sent a payment reminder for ' : 'Emailed ').$this->salesDocumentService->describe($document)." to {$data['to']}", $document, ['source' => 'api']);

        return $this->salesDocumentRepository->loadShown($document);
    }

    public function receiptDraft(Workspace $workspace, int $id): array
    {
        $payment = $this->receipt($workspace, $id);

        return $this->draft($workspace, DocumentType::CustomerReceipt->value, $this->documentEmailText->forReceipt($workspace, $payment),
            $this->documentEmailText->customer($payment->document)['email'] ?? $payment->contact?->email, false);
    }

    public function sendReceipt(Workspace $workspace, int $id, User $user, array $data): SalesPayment
    {
        $payment = $this->receipt($workspace, $id);

        $this->queue($workspace, $payment, $user, DocumentEmail::DOCUMENT, $data, $this->documentEmailText->forReceipt($workspace, $payment));

        ActivityLogService::record('updated', "Emailed receipt {$payment->number} to {$data['to']}", $payment, ['source' => 'api']);

        return $this->salesPaymentRepository->loadShown($payment);
    }

    /** A document its status lets go out this way - otherwise a 409 saying why. */
    private function deliverable(Workspace $workspace, DocumentType $type, int $id, bool $reminder): SalesDocument
    {
        $document = $this->salesDocumentService->find($workspace, $type, $id);

        if (! $this->salesDocumentRules->delivers($document, $reminder ? 'reminder' : 'email', $this->salesDocumentService->today($workspace))) {
            $this->refuse($reminder
                ? 'Only an issued invoice with something still to pay takes a reminder.'
                : 'This cannot be emailed as it is now. A void, cancelled, rejected or expired document is not sent.');
        }

        return $document;
    }

    /** A payment's receipt, while the payment stands. */
    private function receipt(Workspace $workspace, int $id): SalesPayment
    {
        $payment = $this->salesPdfService->receiptFor($workspace, $id);

        if (! $payment->isCompleted()) {
            $this->refuse('This payment was reversed, so its receipt is not sent.');
        }

        return $payment;
    }

    private function draft(Workspace $workspace, string $key, array $values, ?string $to, bool $reminder): array
    {
        $template = $this->emailSettingRepository->forWorkspace($workspace)->template($key);

        return [
            'to'       => $to,
            'subject'  => $this->documentEmailText->fill($template['subject'], $values),
            'body'     => $this->documentEmailText->fill($template['body'], $values),
            'reminder' => $reminder,
        ];
    }

    /** The email written down as queued, and handed to the queue with its text filled in. */
    private function queue(Workspace $workspace, Model $record, User $user, string $kind, array $data, array $values): void
    {
        $email = $this->documentEmailRepository->create($workspace, $record, [
            'kind'    => $kind,
            'to'      => $data['to'],
            'cc'      => $data['cc'] ?? [],
            'subject' => mb_substr($this->documentEmailText->fill($data['subject'], $values), 0, 255),
            'sent_by' => $user->id,
        ]);

        SendDocumentEmail::dispatch($email->id, $this->documentEmailText->fill($data['body'], $values));
    }
}
