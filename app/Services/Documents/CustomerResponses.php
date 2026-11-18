<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Enums\Edition;
use App\Enums\SalesDocumentStatus;
use App\Models\User\CustomerResponse;
use App\Models\User\DocumentLink;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use App\Repositories\Shared\RowLockRepository;
use App\Repositories\User\CustomerResponseRepository;
use App\Repositories\User\DocumentLinkRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\SalesSettingRepository;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\NotificationAudience;
use App\Services\Shared\Notifications\Notifier;
use App\Services\User\Apps\WorkspaceApps;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * What a customer does through a document's secure link (Doc 16 section
 * 16.22 - Phase G2), on the full Sales app: accept or decline a sent
 * quotation that has not expired - it becomes Accepted or Rejected, as when
 * the business marks it - and comment on whatever the link opens, while
 * Settings > Sales allows it. Each is kept with when and where from - the
 * address and browser - logged, and told to the business's users who see
 * that kind of document. A link that no longer works takes nothing: null.
 */
class CustomerResponses
{
    public function __construct(
        protected DocumentLinkRepository $links,
        protected DocumentLinks $documentLinks,
        protected CustomerResponseRepository $responses,
        protected SalesDocumentRepository $documents,
        protected SalesDocumentRules $rules,
        protected SalesSettingRepository $settings,
        protected RowLockRepository $rows,
        protected BusinessCalendar $calendar,
        protected NotificationAudience $audience,
        protected Notifier $notifier,
    ) {}

    /** What the link's page offers under the document: null on Sales Lite, which only shows it. */
    public function screen(DocumentLink $link): ?array
    {
        $workspace = $link->workspace;
        $document  = $link->linkable;

        if (! WorkspaceApps::for($workspace)->has('sales', Edition::Full)) {
            return null;
        }

        $answer = $document->type === DocumentType::Quotation ? $this->responses->answerFor($document) : null;

        return [
            'business'   => $document->business_snapshot['name'] ?? $workspace->name,
            'answerable' => $document->type === DocumentType::Quotation && $this->rules->allows($document, 'accept', $this->today($workspace)),
            'answer'     => $answer ? ['kind' => $answer->kind, 'name' => $answer->name, 'at' => $this->when($workspace, $answer)] : null,
            'commenting' => $this->commenting($workspace),
            'comments'   => $this->responses->commentsFor($document)->map(fn (CustomerResponse $comment) => [
                'name' => $comment->name,
                'body' => $comment->body,
                'at'   => $this->when($workspace, $comment),
            ])->all(),
            'accept_url'  => route('documents.public.accept', $link->token),
            'reject_url'  => route('documents.public.reject', $link->token),
            'comment_url' => route('documents.public.comment', $link->token),
        ];
    }

    /** @param  array{ip: ?string, agent: ?string}  $source */
    public function accept(string $token, ?string $name, array $source): ?string
    {
        return $this->answer($token, CustomerResponse::ACCEPTED, $name, null, $source);
    }

    /** @param  array{ip: ?string, agent: ?string}  $source */
    public function reject(string $token, ?string $name, ?string $reason, array $source): ?string
    {
        return $this->answer($token, CustomerResponse::REJECTED, $name, $reason, $source);
    }

    /** @param  array{ip: ?string, agent: ?string}  $source */
    public function comment(string $token, ?string $name, string $body, array $source): ?string
    {
        $link = $this->working($token);

        if (! $link) {
            return null;
        }

        if (! $this->commenting($link->workspace)) {
            throw ValidationException::withMessages(['response' => 'Comments are not taken through this link. Please contact the business directly.']);
        }

        $document = $link->linkable;
        $response = $this->responses->create($link, ['kind' => CustomerResponse::COMMENT, ...$this->who($name, $source), 'body' => trim($body)]);

        $this->record($document, $response, "commented on {$this->describe($document)}", Str::limit($response->body, 200));

        return "Your comment was sent to {$this->businessOf($link)}.";
    }

    /** A sent quotation answered, once: Accepted, or Rejected with why. */
    private function answer(string $token, string $kind, ?string $name, ?string $reason, array $source): ?string
    {
        $link = $this->working($token);

        if (! $link) {
            return null;
        }

        $accepted = $kind === CustomerResponse::ACCEPTED;
        $reason   = filled($reason) ? trim($reason) : null;

        $response = DB::transaction(function () use ($link, $kind, $accepted, $name, $reason, $source) {
            // Locked, so the business marking it at the same moment does not cross the customer's answer.
            $document = $this->rows->lock($link->linkable);

            if ($document->type !== DocumentType::Quotation || ! $this->rules->allows($document, $accepted ? 'accept' : 'reject', $this->today($link->workspace))) {
                throw ValidationException::withMessages(['response' => 'This quotation can no longer be answered here. Please contact the business directly.']);
            }

            $this->documents->update($document, ['status' => $accepted ? SalesDocumentStatus::Accepted : SalesDocumentStatus::Rejected, 'status_reason' => $reason, 'updated_by' => null]);
            $link->setRelation('linkable', $document);

            return $this->responses->create($link, ['kind' => $kind, ...$this->who($name, $source), 'body' => $reason]);
        });

        $this->record($link->linkable, $response, ($accepted ? 'accepted ' : 'declined ').$this->describe($link->linkable), $reason);

        return $accepted
            ? "Thank you - you accepted this quotation, and {$this->businessOf($link)} has been told."
            : "You declined this quotation, and {$this->businessOf($link)} has been told.";
    }

    private function working(string $token): ?DocumentLink
    {
        $link = $this->links->findByToken($token);

        if (! $link || ! $this->documentLinks->works($link)) {
            return null;
        }

        if (! WorkspaceApps::for($link->workspace)->has('sales', Edition::Full)) {
            throw ValidationException::withMessages(['response' => 'This link only shows the document. Please contact the business directly.']);
        }

        return $link;
    }

    /** In the Activity Log - put down to no one: the customer did it - and told to those who see the document. */
    private function record(SalesDocument $document, CustomerResponse $response, string $what, ?string $body): void
    {
        $customer  = $this->customerOf($document);
        $as        = $response->name ? " (as {$response->name})" : '';
        $workspace = $document->workspace;

        ActivityLogService::recordCustomer($workspace, 'updated', "{$customer} {$what} through its link{$as}", $document, ['source' => 'customer_link', 'response' => $response->id]);

        $this->notifier->send($workspace, $this->audience->withPermission($workspace, $document->type->permissionGroup().'.view', $document->location_id), new Notice(
            trigger: "customers.{$response->kind}",
            key: "customers.{$response->kind}:{$response->id}",
            title: Str::ucfirst("{$customer} {$what}"),
            body: $body ?? 'Through its link'.($response->name ? ", as {$response->name}" : '').'.',
            link: "/sales/{$document->type->kind()}/{$document->id}",
            subject: $document,
            email: true,
        ));
    }

    /** @return array{name: ?string, ip_address: ?string, user_agent: ?string} */
    private function who(?string $name, array $source): array
    {
        return [
            'name'       => filled($name) ? Str::limit(trim($name), 100, '') : null,
            'ip_address' => $source['ip'] ?? null,
            'user_agent' => isset($source['agent']) ? Str::limit($source['agent'], 500, '') : null,
        ];
    }

    private function commenting(Workspace $workspace): bool
    {
        return (bool) $this->settings->forWorkspace($workspace)->link_comments;
    }

    private function today(Workspace $workspace): string
    {
        return $this->calendar->today($workspace)->toDateString();
    }

    /** "15 Nov 2026, 14:05", in the business's time zone. */
    private function when(Workspace $workspace, CustomerResponse $response): string
    {
        return $response->created_at->timezone($this->calendar->timezone($workspace))->format('j M Y, H:i');
    }

    /** "quotation QUO-00003", "sales order SO-00001". */
    private function describe(SalesDocument $document): string
    {
        return Str::lower($document->type->label()).' '.$document->number;
    }

    private function customerOf(SalesDocument $document): string
    {
        return $document->customer_snapshot['name'] ?? $document->contact?->name ?? 'The customer';
    }

    private function businessOf(DocumentLink $link): string
    {
        return $link->linkable->business_snapshot['name'] ?? $link->workspace->name;
    }
}
