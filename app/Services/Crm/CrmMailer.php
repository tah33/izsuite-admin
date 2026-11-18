<?php

namespace App\Services\Crm;

use App\Exceptions\AttachmentMissing;
use App\Exceptions\PdfUnavailable;
use App\Mail\RecordMail;
use App\Models\User\CrmEmail;
use App\Models\User\SalesDocument;
use App\Models\User\WorkspaceFile;
use App\Repositories\User\CrmEmailRepository;
use App\Repositories\User\EmailSettingRepository;
use App\Services\Api\Documents\EmailSettingService;
use App\Services\Documents\DocumentPdf;
use App\Services\Files\FileStorage;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a queued e-mail from a CRM record (see SendCrmEmail, Doc 17 Phase
 * F3a) through the platform's mail server: from its address, under the
 * business's sender name, with replies to the business - with each sales
 * document's PDF, made as it goes so it shows the document as it is then,
 * and each file. Without one of them it is not sent.
 */
class CrmMailer
{
    public function __construct(
        protected CrmEmailRepository $emails,
        protected EmailSettingRepository $emailSettingRepository,
        protected EmailSettingService $emailSettingService,
        protected DocumentPdf $documentPdf,
        protected FileStorage $fileStorage,
    ) {}

    /** Sent once: a retry after it went does nothing. A failure is the queue's to retry. */
    public function deliver(int $emailId): void
    {
        $email = $this->emails->findToSend($emailId);

        if (! $email || $email->status === CrmEmail::SENT) {
            return;
        }

        $workspace = $email->workspace;
        $setting   = $this->emailSettingRepository->forWorkspace($workspace);
        $attached  = collect($email->attachments ?? []);

        $documents = $this->emails->documents($email, $attached->where('kind', 'document')->pluck('id')->all())
            ->map(function (SalesDocument $document) use ($workspace) {
                [$filename, $pdf] = $this->documentPdf->sales($workspace, $document);

                return ['name' => $filename, 'data' => $pdf, 'mime' => 'application/pdf'];
            });
        $files = $this->emails->files($email, $attached->where('kind', 'file')->pluck('id')->all())
            ->map(fn (WorkspaceFile $file) => ['name' => $file->name, 'data' => $this->fileStorage->contents($file) ?? throw new AttachmentMissing, 'mime' => $file->mime_type ?: 'application/octet-stream']);

        if ($documents->count() + $files->count() < $attached->count()) {
            throw new AttachmentMissing;
        }

        Mail::to($email->to)->cc($email->cc ?? [])->send(new RecordMail(
            $email->subject,
            $email->body,
            $this->emailSettingService->senderName($workspace, $setting),
            $this->emailSettingService->replyTo($workspace, $setting),
            [...$documents->all(), ...$files->all()],
        ));

        $this->emails->update($email, ['status' => CrmEmail::SENT, 'sent_at' => now(), 'failure' => null]);
    }

    /** Given up on: its timeline says it did not go, and why in a word - never the server's own message. */
    public function failed(int $emailId, ?\Throwable $e): void
    {
        $email = $this->emails->findToSend($emailId);

        if ($email && $email->status !== CrmEmail::SENT) {
            $this->emails->update($email, [
                'status'  => CrmEmail::FAILED,
                'failure' => match (true) {
                    $e instanceof PdfUnavailable    => 'A PDF could not be made.',
                    $e instanceof AttachmentMissing => 'Something it carried is no longer there.',
                    default                         => 'The mail server did not accept it.',
                },
            ]);
        }
    }
}
