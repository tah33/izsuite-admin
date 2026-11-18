<?php

namespace App\Services\Shared;

use App\Mail\AccountNoticeMail;
use App\Models\User\SupportSession;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\SupportSessionRepository;
use App\Repositories\User\UserRepository;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\Notifier;
use App\Support\SupportAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Support sessions (Doc 15 section 15.22), in place of Login as: izSuite
 * support in a business, for one of its support cases and a reason - read
 * only, or with the edit access the customer agreed to - until it ends by
 * itself, or is ended.
 *
 * The admin panel makes a one-time code and sends the admin's browser to the
 * app with it; the app trades it here for an API session as the business's
 * owner, held to that business and to what SupportAccess allows. The business's
 * Activity Log keeps the session's start and end, the sensitive areas it
 * opened, and anything it changed, as izSuite support's; the owner is told.
 */
class SupportSessionService
{
    /** How long a link works: long enough for its tab to open. */
    public const CODE_TTL_SECONDS = 60;

    public const MAX_HOURS        = 4;

    private const INVALID_CODE    = 'This support session link has expired or was already used. Open it again from the admin panel.';

    public function __construct(
        protected SupportSessionRepository $sessions,
        protected UserRepository $users,
        protected Notifier $notifier,
    ) {}

    /** Admin > Support Sessions: a page of them, by ListSupportSessionsRequest's filters. */
    public function page(array $filters): LengthAwarePaginator
    {
        return $this->sessions->page($filters);
    }

    public function business(int $id): Workspace
    {
        return $this->sessions->findWorkspace($id) ?? abort(404);
    }

    /**
     * The link into a session, for the admin who opened it - and only them, as
     * what the session does is logged as theirs.
     */
    public function enter(int $id, User $admin): string
    {
        $session = $this->sessions->find($id) ?? abort(404);

        abort_unless($session->user_id === $admin->id, 403, 'Only the admin who opened a support session enters it.');

        return $this->link($session, $admin);
    }

    public function endById(int $id, User $admin): void
    {
        $session = $this->sessions->find($id) ?? abort(404);

        abort_unless($session->isLive(), 422, 'This support session has ended already.');

        $this->end($session, SupportSession::ENDED_BY_ADMIN, $admin);
    }

    /** Opens a session in $workspace for $admin: $data has ticket_id, reason, hours and, with $edit, customer_authorization. */
    public function open(Workspace $workspace, User $admin, array $data, bool $edit): SupportSession
    {
        if (! $this->sessions->isOpenCaseOf($workspace, (int) $data['ticket_id'])) {
            throw ValidationException::withMessages(['ticket_id' => 'Choose one of this business\'s open cases.']);
        }

        $session = $this->sessions->create([
            'workspace_id'           => $workspace->id,
            'ticket_id'              => (int) $data['ticket_id'],
            'user_id'                => $admin->id,
            'admin_name'             => $admin->name,
            'access'                 => $edit ? SupportSession::EDIT : SupportSession::READ_ONLY,
            'reason'                 => $data['reason'],
            'customer_authorization' => $edit ? $data['customer_authorization'] : null,
            'started_at'             => now(),
            'expires_at'             => now()->addHours(min(self::MAX_HOURS, max(1, (int) $data['hours']))),
        ]);
        $session->setRelation('workspace', $workspace);

        $how = $edit ? 'with edit access' : 'read only';

        ActivityLogService::recordSupport($workspace, 'support_started', "Started a support session, {$how}, for case #{$session->ticket_id} until {$this->until($session)}: {$session->reason}", $session->id, $admin->name, array_filter([
            'customer_authorization' => $session->customer_authorization,
        ]));
        ActivityLogService::record('support_started', "Opened a support session in business \"{$workspace->name}\", {$how}", $session, [
            'reason' => $session->reason, 'workspace_id' => $workspace->id, 'case' => $session->ticket_id, 'customer_authorization' => $session->customer_authorization,
        ], $admin);

        $this->tellOwner($workspace, $session);

        return $session;
    }

    /** The app's link that opens the session, with its one-time code. */
    public function link(SupportSession $session, User $admin): string
    {
        abort_unless($session->isLive(), 422, 'This support session has ended.');

        $code = Str::random(64);

        Cache::put($this->codeKey($code), [
            'session_id' => $session->id,
            // Made on the admin's own request, so it names the host they are signed in on.
            'back_url'   => route('admin.businesses.show', $session->workspace_id),
        ], self::CODE_TTL_SECONDS);

        ActivityLogService::record('support_entered', "Entered the support session in business \"{$session->workspace?->name}\"", $session, ['workspace_id' => $session->workspace_id], $admin);

        return rtrim((string) config('app.frontend_url'), '/').'/support-session?code='.$code;
    }

    /**
     * Trades a code from link() for an API session as the business's owner -
     * once - which ends when the support session does.
     *
     * @return array{user: User, token: \Laravel\Sanctum\NewAccessToken, session: SupportSession, back_url: string}
     */
    public function redeem(string $code): array
    {
        // Pulled, not read: a code is spent the moment it is tried.
        $grant   = Cache::pull($this->codeKey($code));
        $session = $grant ? $this->sessions->find((int) $grant['session_id']) : null;
        $owner   = $session?->workspace ? $this->users->findById($session->workspace->user_id) : null;

        abort_if(! $session || ! $session->isLive() || ! $owner, 401, self::INVALID_CODE);

        $token = $this->users->createToken($owner, $session->tokenName(), ['*'], $session->expires_at, request()->ip(), request()->userAgent());

        Cache::put($this->backKey($token->accessToken->id), $grant['back_url'], $session->expires_at);

        return ['user' => $this->users->loadRole($owner), 'token' => $token, 'session' => $session, 'back_url' => $grant['back_url']];
    }

    /** The session $token carries, if it is a support session's token. */
    public function forToken(PersonalAccessToken $token): ?SupportSession
    {
        if (! str_starts_with((string) $token->name, SupportAccess::TOKEN_PREFIX)) {
            return null;
        }

        $id = (int) Str::after((string) $token->name, SupportAccess::TOKEN_PREFIX);

        return $id > 0 ? $this->sessions->find($id) : null;
    }

    /** @return array{session: SupportSession, back_url: string}|null what the app is told of the session $user's request came on */
    public function describe(User $user): ?array
    {
        $token   = $user->currentAccessToken();
        $session = $token instanceof PersonalAccessToken ? $this->forToken($token) : null;

        return $session ? ['session' => $session, 'back_url' => Cache::get($this->backKey($token->id)) ?? route('admin.overview')] : null;
    }

    /** Ends it now: its tokens go, and the business's log says how. $admin when an admin ended it. */
    public function end(SupportSession $session, string $reason, ?User $admin = null): void
    {
        if ($session->ended_at !== null) {
            return;
        }

        $this->sessions->update($session, ['ended_at' => now(), 'end_reason' => $reason]);
        $this->sessions->revokeTokens($session);

        $how = [
            SupportSession::ENDED_EXPIRED  => 'ended at its time',
            SupportSession::ENDED_BY_ADMIN => 'was ended from the admin panel',
            SupportSession::ENDED_IN_APP   => 'was ended in the app',
        ][$reason] ?? 'ended';

        ActivityLogService::recordSupport($session->workspace, 'support_ended', "The support session for case #{$session->ticket_id} {$how}", $session->id, $session->admin_name, [
            'areas' => $session->areas ?? [],
        ]);

        if ($admin) {
            ActivityLogService::record('support_ended', "Ended the support session in business \"{$session->workspace->name}\"", $session, ['workspace_id' => $session->workspace_id], $admin);
        }
    }

    /** Ends each session past its end that nothing has ended yet. @return int how many */
    public function closeExpired(): int
    {
        $due = $this->sessions->expiredUnended();

        foreach ($due as $session) {
            $this->end($session, SupportSession::ENDED_EXPIRED);
        }

        return $due->count();
    }

    /** What the API answers instead when $session may not do $request; null when it may. */
    public function refusal(Request $request, SupportSession $session): ?JsonResponse
    {
        $message = match (true) {
            $request->routeIs(...SupportAccess::CLOSED) || str_contains((string) $request->route()?->getName(), '.export')
                => 'This is not open to izSuite support.',
            $request->isMethodSafe() || $request->routeIs('api.auth.logout')
                => null,
            ! $session->canEdit()
                => 'This support session is read only: nothing can be changed.',
            $request->routeIs(...SupportAccess::NEVER_CHANGED)
                => 'izSuite support cannot change this, even with edit access.',
            default => null,
        };

        return $message ? response()->json(['message' => $message, 'support_session' => true], 403) : null;
    }

    /** The first time a session opens a sensitive area, the business's Activity Log says so. */
    public function noteArea(Request $request, SupportSession $session): void
    {
        foreach (SupportAccess::AREAS as $area => $routes) {
            if ($request->routeIs(...$routes) && ! in_array($area, $session->areas ?? [], true)) {
                $this->sessions->update($session, ['areas' => [...($session->areas ?? []), $area]]);
                ActivityLogService::record('support_viewed', "Opened {$area} in the support session", $session->workspace, ['area' => $area]);

                return;
            }
        }
    }

    /** The owner hears of the session: in the bell, and by e-mail. */
    private function tellOwner(Workspace $workspace, SupportSession $session): void
    {
        $owner = $this->users->findById($workspace->user_id);

        if (! $owner) {
            return;
        }

        $title = $session->canEdit() ? "izSuite support can make changes in {$workspace->name}" : "izSuite support is viewing {$workspace->name}";
        $lines = [
            "{$session->admin_name} from izSuite support opened your business ".($session->canEdit() ? 'with the edit access you agreed to' : 'to look, not to change anything').", until {$this->until($session)}, for case #{$session->ticket_id}.",
            "Why: {$session->reason}",
            'The session ends by itself. Settings > Activity Log shows what it opened, and anything it changed.',
        ];

        $this->notifier->send($workspace, [$owner], new Notice(
            trigger: 'izsuite.support',
            key: "izsuite.support.{$session->id}",
            title: $title,
            body: implode(' ', $lines),
            link: "/support/{$session->ticket_id}",
            subject: $workspace,
        ));

        Mail::to($owner->email)->queue(new AccountNoticeMail($owner->first_name ?: $owner->name, $title, $lines));
    }

    private function until(SupportSession $session): string
    {
        return $session->expires_at->copy()->setTimezone(app_display_timezone())->format('j M Y, H:i T');
    }

    private function codeKey(string $code): string
    {
        return 'support-session:'.hash('sha256', $code);
    }

    private function backKey(int $tokenId): string
    {
        return 'support-session-back:'.$tokenId;
    }
}
