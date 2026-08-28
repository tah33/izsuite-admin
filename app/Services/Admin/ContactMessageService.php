<?php

namespace App\Services\Admin;

use App\Mail\ContactMessageReplied;
use App\Models\Admin\ContactMessage;
use App\Repositories\Admin\ContactMessageRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactMessageService
{
    public function __construct(
        protected ContactMessageRepository $contactMessageRepository,
    ) {}

    public function getPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->contactMessageRepository->getPaginated($perPage);
    }

    public function find(int $id): ?ContactMessage
    {
        return $this->contactMessageRepository->find($id);
    }

    /**
     * Reply to a contact message.
     */
    public function reply(ContactMessage $contactMessage, string $reply, ?string $subject = null): ContactMessage
    {
        $updated = $this->contactMessageRepository->update($contactMessage, [
            'admin_reply'   => $reply,
            'reply_subject' => $subject ?? ('Re: '.$contactMessage->subject),
            'replied_by'    => auth()->id(),
            'replied_at'    => now(),
            'status'        => 'replied',
        ]);

        // Send Email
        rescue(
            fn () => Mail::to($contactMessage->email)->send(new ContactMessageReplied($updated)),
            function (\Throwable $e): void {
                Log::error('Failed to send contact reply email: '.$e->getMessage());
            },
            report: false
        );

        ActivityLogService::record('replied', "Replied to contact message #{$contactMessage->id}", $updated);

        return $updated;
    }
}
