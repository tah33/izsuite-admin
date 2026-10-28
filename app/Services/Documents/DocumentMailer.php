<?php

namespace App\Services\Documents;

use App\Exceptions\PdfUnavailable;
use App\Mail\DocumentMail;
use App\Models\User\DocumentEmail;
use App\Models\User\SalesPayment;
use App\Repositories\User\DocumentEmailRepository;
use App\Repositories\User\EmailSettingRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\SalesPaymentRepository;
use App\Services\Api\Documents\EmailSettingService;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a queued document email (see SendDocumentEmail) through the
 * platform's mail server: from its address, under the business's sender name,
 * with replies to the business, and the document's PDF - made as the email
 * goes, so it shows the document as it is then.
 */
class DocumentMailer
{
    public function __construct(
        protected DocumentEmailRepository $documentEmailRepository,
        protected EmailSettingRepository $emailSettingRepository,
        protected EmailSettingService $emailSettingService,
        protected SalesDocumentRepository $salesDocumentRepository,
        protected SalesPaymentRepository $salesPaymentRepository,
        protected DocumentPdf $documentPdf,
    ) {}

    /** Sent once: a retry after it went does nothing. A failure is the queue's to retry. */
    public function deliver(int $emailId, string $body): void
    {
        $email = $this->documentEmailRepository->findWithRecord($emailId);

        if (! $email || $email->status === DocumentEmail::SENT || ! $email->emailable) {
            return;
        }

        $workspace = $email->workspace;
        $record    = $email->emailable;
        $setting   = $this->emailSettingRepository->forWorkspace($workspace);

        [$filename, $pdf] = $record instanceof SalesPayment
            ? $this->documentPdf->receipt($workspace, $this->salesPaymentRepository->loadForReceipt($record))
            : $this->documentPdf->sales($workspace, $this->salesDocumentRepository->loadForPdf($record));

        Mail::to($email->to)->cc($email->cc ?? [])->send(new DocumentMail(
            $email->subject,
            $body,
            $this->emailSettingService->senderName($workspace, $setting),
            $this->emailSettingService->replyTo($workspace, $setting),
            $filename,
            $pdf,
        ));

        $this->documentEmailRepository->update($email, ['status' => DocumentEmail::SENT, 'sent_at' => now(), 'failure' => null]);
    }

    /** Given up on: the page says it did not go, and why in a word - never the server's own message. */
    public function failed(int $emailId, ?\Throwable $e): void
    {
        $email = $this->documentEmailRepository->findWithRecord($emailId);

        if ($email && $email->status !== DocumentEmail::SENT) {
            $this->documentEmailRepository->update($email, [
                'status'  => DocumentEmail::FAILED,
                'failure' => $e instanceof PdfUnavailable ? 'The PDF could not be made.' : 'The mail server did not accept it.',
            ]);
        }
    }
}
