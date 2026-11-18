<?php

namespace App\Services\Admin\Privacy;

use App\Jobs\BuildPrivacyExport;
use App\Mail\AccountNoticeMail;
use App\Models\Admin\PrivacyRequest;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Admin\PrivacyRequestRepository;
use App\Repositories\User\UserRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Workspaces\BusinessStatusService;
use App\Support\BusinessStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Privacy Requests (Doc 15 section 15.24 - Phase G1): a business's
 * data exported, an account closed, data corrected. Each is logged as it
 * came, then worked only once the person asking is checked - "privacy
 * requests require identity verification" - and ends completed, with what
 * was done, or rejected, with why. Every step is in the audit log.
 *
 * - An export is a ZIP of the business's data as CSV files, built in the
 *   background (BusinessDataExport) and e-mailed as a link that lasts the
 *   configured days (privacy_export_days, 7 by default); then the file goes.
 * - A closure closes the account - it signs in no more - and the businesses
 *   it owns. Their records are kept: closing never deletes (section 15.27).
 * - A correction records what was corrected.
 */
class PrivacyRequestService
{
    public function __construct(
        protected PrivacyRequestRepository $requests,
        protected UserRepository $users,
        protected WorkspaceRepository $workspaces,
        protected BusinessStatusService $statuses,
        protected BusinessDataExport $exports,
    ) {}

    public function index(array $filters): LengthAwarePaginator
    {
        return $this->requests->paginated($filters);
    }

    public function findOrFail(int $id): PrivacyRequest
    {
        return $this->requests->findOrFail($id);
    }

    /** The request's page: it, and what closing its account would close. */
    public function page(int $id): array
    {
        $request = $this->requests->findOrFail($id);

        return [
            'request'    => $request,
            'businesses' => $request->type === PrivacyRequest::CLOSURE && $request->user ? $this->requests->ownedBusinesses($request->user) : collect(),
            'exportDays' => $this->exportDays(),
        ];
    }

    /**
     * A request as it came. $data is validated (StorePrivacyRequestRequest):
     * the account by its e-mail and the business by its BW- ID, as its type
     * needs them.
     */
    public function log(array $data, User $admin): PrivacyRequest
    {
        $account  = filled($data['account_email'] ?? null) ? $this->users->findByEmail(trim($data['account_email'])) : null;
        $business = filled($data['business'] ?? null) ? $this->workspaces->find((int) preg_replace('/\D/', '', $data['business'])) : null;

        $this->checkSubjects($data['type'], $data, $account, $business);

        $received = Carbon::parse($data['received_on']);
        $request  = $this->requests->create([
            'type'            => $data['type'],
            'status'          => PrivacyRequest::RECEIVED,
            'user_id'         => $account?->id,
            'workspace_id'    => $business?->id,
            'requester_name'  => trim($data['requester_name']),
            'requester_email' => trim($data['requester_email']),
            'received_via'    => $data['received_via'],
            'received_on'     => $received->toDateString(),
            'due_on'          => $received->copy()->addDays(PrivacyRequest::DUE_DAYS)->toDateString(),
            'details'         => trim($data['details']),
            'created_by'      => $admin->id,
        ]);

        ActivityLogService::record('created', "Logged privacy request {$request->number()}: ".PrivacyRequest::TYPES[$request->type]." for {$request->requester_name}", $request, null, $admin);

        return $request;
    }

    /** The person asking is who they say: how they were checked is kept. */
    public function verify(PrivacyRequest $request, string $check, User $admin): void
    {
        $this->expect($request, [PrivacyRequest::RECEIVED], 'Only a request just received has its asker checked.');

        $this->requests->update($request, ['status' => PrivacyRequest::VERIFIED, 'identity_check' => trim($check), 'verified_by' => $admin->id, 'verified_at' => now()]);

        ActivityLogService::record('verified', "Checked who asked privacy request {$request->number()}", $request, ['identity_check' => trim($check)], $admin);
    }

    /** Starts the business's export, built in the background; its link goes to the asker once it is ready. */
    public function startExport(PrivacyRequest $request, User $admin): void
    {
        $this->expect($request, [PrivacyRequest::VERIFIED], 'Check who is asking before exporting anything.', PrivacyRequest::EXPORT);
        $request->workspace ?? throw ValidationException::withMessages(['request' => 'The business of this request is gone, so there is nothing to export.']);

        $this->requests->update($request, ['status' => PrivacyRequest::IN_PROGRESS]);

        ActivityLogService::record('exported', "Started the data export of {$request->workspace->businessId()} for privacy request {$request->number()}", $request, null, $admin);

        BuildPrivacyExport::dispatch($request->id, $admin->id)->afterCommit();
    }

    /**
     * Builds $request's export (BuildPrivacyExport), e-mails its link and
     * completes the request. The request says it was sent only once the link
     * is made and the e-mail queued; anything failing before leaves it being
     * prepared, without the file, for the job to try again.
     */
    public function buildExport(PrivacyRequest $request, ?User $admin): void
    {
        if ($request->status !== PrivacyRequest::IN_PROGRESS || ! $request->workspace) {
            return;
        }

        $built   = $this->exports->build($request->workspace, $request->number());
        $expires = now()->addDays($this->exportDays());

        try {
            $this->tell($request, 'Your izSuite data export is ready', [
                "As you asked, here is the data of {$request->workspace->name} as CSV files, in a ZIP.",
                'The link works until '.$expires->format('j F Y').'. After that the file is deleted, and you can ask again.',
                'If you did not ask for this, write to izSuite support at once.',
            ], 'Download the export', URL::temporarySignedRoute('privacy-exports.download', $expires, ['id' => $request->id]));
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($built['path']);

            throw $e;
        }

        $this->requests->update($request, [
            'status'            => PrivacyRequest::COMPLETED,
            'export_path'       => $built['path'],
            'export_size'       => $built['size'],
            'export_ready_at'   => now(),
            'export_expires_at' => $expires,
            'outcome'           => "The data of {$request->workspace->name} ({$built['files']} CSV files) was sent to {$request->requester_email} as a link that works until ".$expires->format('j M Y H:i').' UTC.',
            'completed_by'      => $admin?->id,
            'completed_at'      => now(),
        ]);

        ActivityLogService::record('exported', "The data export for privacy request {$request->number()} is ready and its link sent to {$request->requester_email}", $request, ['files' => $built['files'], 'size' => $built['size']], $admin);
    }

    /** An export that could not be built: back to checked, to try again. */
    public function exportFailed(PrivacyRequest $request, string $why, ?User $admin): void
    {
        if ($request->status !== PrivacyRequest::IN_PROGRESS) {
            return;
        }

        $this->requests->update($request, ['status' => PrivacyRequest::VERIFIED]);

        ActivityLogService::record('failed', "The data export for privacy request {$request->number()} could not be built: {$why}", $request, ['source' => 'system'], $admin);
    }

    /** The export's file, while it lasts, for $who: an admin (logged), or the asker by the link (counted). */
    public function exportFile(PrivacyRequest $request, ?User $admin = null): string
    {
        abort_unless($request->exportAvailable() && Storage::disk('local')->exists($request->export_path), 410, 'This export has expired. Ask izSuite for another.');

        if ($admin) {
            ActivityLogService::record('downloaded', "Downloaded the data export of privacy request {$request->number()}", $request, null, $admin);
        } else {
            $this->requests->countDownload($request);
        }

        return Storage::disk('local')->path($request->export_path);
    }

    /** Deletes each export whose days are over (privacy:purge-exports). @return int how many */
    public function purgeExpired(): int
    {
        $expired = $this->requests->expiredExports(now());

        foreach ($expired as $request) {
            Storage::disk('local')->delete($request->export_path);
            $this->requests->update($request, ['export_path' => null]);
        }

        return $expired->count();
    }

    /**
     * Closes the account of $request, and the businesses it owns: nobody
     * signs in to it again, and nobody opens those businesses. Their records
     * are kept. A business still subscribed has its subscription cancelled
     * first, in Admin > Subscriptions.
     */
    public function closeAccount(PrivacyRequest $request, User $admin): void
    {
        $this->expect($request, [PrivacyRequest::VERIFIED], 'Check who is asking before closing the account.', PrivacyRequest::CLOSURE);
        $account = $request->user ?? throw ValidationException::withMessages(['request' => 'The account of this request is gone.']);

        if ($account->status === User::CLOSED) {
            throw ValidationException::withMessages(['request' => 'This account is closed already.']);
        }

        $owned = $this->requests->ownedBusinesses($account);

        if (($subscribed = $this->requests->subscribed($owned))->isNotEmpty()) {
            throw ValidationException::withMessages(['request' => 'Cancel the subscription of '.$subscribed->pluck('name')->implode(', ').' first, in Admin > Subscriptions: a closed business is not billed.']);
        }

        $open   = $owned->reject(fn (Workspace $business) => $business->status === BusinessStatus::CLOSED);
        $reason = "Closed with its owner's account, as they asked (privacy request {$request->number()}).";

        DB::transaction(function () use ($request, $account, $open, $reason, $admin) {
            $this->users->forceUpdate($account, ['status' => User::CLOSED]);
            $this->users->revokeTokens($account);

            foreach ($open as $business) {
                $this->statuses->closeNow($business, $reason, $admin);
            }

            $this->requests->update($request, [
                'status'       => PrivacyRequest::COMPLETED,
                'outcome'      => 'The account is closed and signs in no more.'.($open->isEmpty() ? '' : ' Its businesses '.$open->pluck('name')->implode(', ').' are closed.').' Their records are kept.',
                'completed_by' => $admin->id,
                'completed_at' => now(),
            ]);
        });

        ActivityLogService::record('closed', "Closed the account of {$account->email} for privacy request {$request->number()}", $request, ['businesses' => $open->pluck('id')->all()], $admin);

        $this->tell($request, 'Your izSuite account is closed', [
            'As you asked, your izSuite account is closed: nobody can sign in to it any more.',
            ...($open->isEmpty() ? [] : ['The businesses it owned are closed too. Their records are kept, as the law may require.']),
            'If you did not ask for this, write to izSuite support at once.',
        ]);
    }

    /** A correction, done: $outcome says what was corrected, and goes to the asker when $notify. */
    public function complete(PrivacyRequest $request, string $outcome, bool $notify, User $admin): void
    {
        $this->expect($request, [PrivacyRequest::VERIFIED], 'Check who is asking before completing the request.', PrivacyRequest::CORRECTION);

        $this->requests->update($request, ['status' => PrivacyRequest::COMPLETED, 'outcome' => trim($outcome), 'completed_by' => $admin->id, 'completed_at' => now()]);

        ActivityLogService::record('completed', "Completed privacy request {$request->number()}", $request, ['outcome' => trim($outcome), 'notified' => $notify], $admin);

        if ($notify) {
            $this->tell($request, 'Your data is corrected', ['As you asked, we corrected your data:', trim($outcome)]);
        }
    }

    /** Refused, with $reason - for the asker too, when $notify. */
    public function reject(PrivacyRequest $request, string $reason, bool $notify, User $admin): void
    {
        $this->expect($request, [PrivacyRequest::RECEIVED, PrivacyRequest::VERIFIED], 'Only a request still open, and not being exported, can be rejected.');

        $this->requests->update($request, ['status' => PrivacyRequest::REJECTED, 'outcome' => trim($reason), 'completed_by' => $admin->id, 'completed_at' => now()]);

        ActivityLogService::record('rejected', "Rejected privacy request {$request->number()}", $request, ['reason' => trim($reason), 'notified' => $notify], $admin);

        if ($notify) {
            $this->tell($request, 'About your privacy request', ['We could not carry out your request ('.strtolower(PrivacyRequest::TYPES[$request->type]).'):', trim($reason)]);
        }
    }

    /** Days an export's link lasts: the configured ones, 7 by default. */
    public function exportDays(): int
    {
        return max(1, (int) setting('privacy_export_days', 7));
    }

    /** The account and business $type needs, each found, and the account a member of the business. */
    private function checkSubjects(string $type, array $data, ?User $account, ?Workspace $business): void
    {
        $errors = [];

        if (filled($data['account_email'] ?? null) && (! $account || $account->isAdmin())) {
            $errors['account_email'] = 'No customer account has this e-mail.';
        }

        if (filled($data['business'] ?? null) && ! $business) {
            $errors['business'] = 'No business has this ID.';
        }

        $errors += match ($type) {
            PrivacyRequest::EXPORT  => $business ? [] : ['business' => 'Name the business whose data is to be exported, by its BW- ID.'],
            PrivacyRequest::CLOSURE => $account ? [] : ['account_email' => 'Name the account to close, by its e-mail.'],
            default                 => $account || $business ? [] : ['account_email' => 'Name the account or the business whose data is to be corrected.'],
        };

        if (! $errors && $type === PrivacyRequest::EXPORT && $account && ! $this->requests->belongsTo($account, $business)) {
            $errors['account_email'] = 'This account is not the owner or a member of that business.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Refuses unless $request is in one of $statuses - and, given $type, of that type. */
    private function expect(PrivacyRequest $request, array $statuses, string $message, ?string $type = null): void
    {
        if (($type && $request->type !== $type) || ! in_array($request->status, $statuses, true)) {
            throw ValidationException::withMessages(['request' => $message]);
        }
    }

    /** The person asking hears of it, by e-mail. */
    private function tell(PrivacyRequest $request, string $title, array $lines, ?string $actionText = null, ?string $actionUrl = null): void
    {
        Mail::to($request->requester_email)->queue(new AccountNoticeMail(strtok($request->requester_name, ' ') ?: $request->requester_name, $title, $lines, $actionText, $actionUrl));
    }
}
