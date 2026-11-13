<?php

namespace App\Http\Resources\Support;

use App\Models\User\Ticket;
use App\Models\User\TicketMessage;
use App\Support\HtmlText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * One support case in the app (Doc 15 section 15.22), with its messages:
 * each one's writer - "izSuite support" with staff's first name, or the
 * person in the business - and its words as plain text. Staff's editor
 * markup is turned to text here, so the app never shows markup.
 *
 * @property Ticket $resource
 */
class SupportCaseDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id'           => $this->id,
            'subject'      => $this->subject,
            'status'       => $this->status,
            'status_label' => $this->statusLabel(),
            'opened_by'    => $this->user?->name,
            'opened_at'    => $this->created_at?->toIso8601String(),
            'can_reply'    => $this->status !== Ticket::CLOSED,
            'messages'     => $this->messages->map(fn (TicketMessage $message) => [
                'id'          => $message->id,
                'from_staff'  => $message->by_staff,
                'author'      => $message->by_staff ? ($message->user?->first_name ?: 'izSuite') : ($message->user?->name ?? 'Someone who left'),
                'mine'        => ! $message->by_staff && $message->user_id === $viewer?->id,
                'body'        => $message->by_staff ? HtmlText::of($message->message) : (string) $message->message,
                'attachments' => collect($message->attachment_path ?? [])->map(fn (string $path) => [
                    'name' => basename($path),
                    'url'  => Storage::disk('public')->url($path),
                ])->values(),
                'created_at'  => $message->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
