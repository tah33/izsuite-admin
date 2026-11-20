<?php

namespace App\Services\Api\Pos;

use App\Models\User\PosRegister;
use App\Models\User\PosSession;
use App\Models\User\PosSessionClosing;
use App\Models\User\Workspace;
use App\Repositories\User\PosCashMovementRepository;
use App\Repositories\User\PosRegisterRepository;
use App\Repositories\User\PosSessionRepository;
use App\Repositories\User\PosSettingRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Documents\DocumentPdf;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * POS sessions (Doc 18 sections 18.6, 18.27 and 18.28 - Phases B1 and B2a).
 *
 * A cashier opens one on an active register they are a cashier of, at a
 * location they may use, with the opening cash float - a float, not income -
 * and notes: one session open per register, and per cashier.
 *
 * Its cashier closes it with the cash counted - or a supervisor, who
 * approves variances, closes anyone's (an abandoned one), saying why. The
 * cash expected comes from PosSessionTotals, and the variance - counted less
 * expected - needs a reason when it is not nil. The closing is kept as the
 * session's Z report, never changed. A variance beyond Settings → Point of
 * Sale's limit leaves the session Closing until someone who approves
 * variances approves it; one who may approve them closes it at once.
 * Counted, the register and the cashier are free for the next session.
 * When the business counts by notes and coins (Phase B2b), the cash counted
 * is the total of how many of each of the session currency's notes and
 * coins, and the Z report keeps the count.
 *
 * Reopen Closed Sessions opens a counted one again, with a reason, while its
 * register is active and neither the register nor its cashier has another
 * session open: its Z report is marked reopened, and its next closing makes
 * a new one.
 *
 * Sessions are seen where their register's location may be used. Each
 * opening, closing, approval and reopening is logged.
 *
 * Phase F3: its X report while it is open - its totals so far - and, once
 * counted, its Z report, each on the receipt's roll; each one made is logged.
 */
class PosSessionService
{
    use RefusesConflicts;

    public function __construct(
        protected PosSessionRepository $sessions,
        protected PosRegisterRepository $registers,
        protected PosSettingRepository $settings,
        protected PosSessionTotals $totals,
        protected WorkspaceAccess $access,
        protected PosSettingService $posSettings,
        protected PosCashMovementRepository $movements,
        protected DocumentPdf $documentPdf,
    ) {}

    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->sessions->paginateFor($workspace, $this->access->locations(), $filters, $perPage);
    }

    /**
     * The Sessions page's choices: the registers it can be filtered by, each
     * with why the user may not open a session on it now - null when they
     * may - and the user's own open session, if any.
     *
     * @return array{registers: list<array{register: PosRegister, refusal: ?string}>, cashiers: list<User>, mine: ?PosSession}
     */
    public function options(Workspace $workspace): array
    {
        $userId = $this->userId();
        $live   = $this->sessions->liveRegisterIds($workspace);

        $registers = $this->registers->getFor($workspace, $this->access->locations());

        return [
            'registers' => $registers
                ->map(fn (PosRegister $register) => ['register' => $register, 'refusal' => $this->refusal($register, $userId, in_array($register->id, $live, true))])
                ->all(),
            // Doc 18 Phase G1: their cashiers, by name - what POS's pages filter by.
            'cashiers'  => $registers->flatMap(fn (PosRegister $register) => $register->cashiers)->unique('id')->sortBy(fn ($user) => $user->name)->values()->all(),
            'mine'      => $this->sessions->liveOf($workspace, $userId),
        ];
    }

    /** $data is validated (see OpenPosSessionRequest). The new session's page, as show() gives it. */
    public function open(Workspace $workspace, array $data): array
    {
        $userId = $this->userId();

        $session = DB::transaction(function () use ($workspace, $data, $userId): PosSession {
            // The register stays as it is, and takes no other session, until this one is open.
            $register = $this->registers->lockFor($workspace, (int) $data['pos_register_id']);

            if (! $register || ! $this->access->usesLocation($register->location_id)) {
                throw ValidationException::withMessages(['pos_register_id' => 'Choose one of the registers you sell at.']);
            }

            $refusal = $this->refusal($register, $userId, $this->sessions->liveAt($register) !== null);

            if ($refusal !== null) {
                throw ValidationException::withMessages(['pos_register_id' => $refusal]);
            }

            $mine = $this->sessions->liveOf($workspace, $userId);

            if ($mine) {
                $this->refuse("Your session {$mine->number} at {$mine->register->name} is still open: close it first.");
            }

            return $this->sessions->open($workspace, $register, [
                'cashier_id'    => $userId,
                'currency'      => $register->currency,
                'opened_at'     => now(),
                'opening_float' => bcadd((string) $data['opening_float'], '0', 2),
                'opening_notes' => trim((string) ($data['opening_notes'] ?? '')) ?: null,
            ]);
        });

        $session = $this->find($workspace, $session->id);

        ActivityLogService::record('created', "Opened the POS session {$session->number} at the register \"{$session->register->name}\" ({$session->register->code})", $session, ['source' => 'api']);

        return $this->show($workspace, $session->id);
    }

    /**
     * A session's page: it, what it has taken so far while it is open (its Z
     * report says it once counted), whether it is the user's own, and what
     * the user may do with it.
     *
     * While it is open, the notes and coins it is counted by - null when it
     * is counted as one amount.
     *
     * @return array{session: PosSession, totals: ?array, own: bool, closes: bool, approves: bool, reopens: bool, denominations: ?list<string>}
     */
    public function show(Workspace $workspace, int $id): array
    {
        $session = $this->find($workspace, $id);
        $counted = in_array($session->status, PosSession::COUNTED, true);

        return [
            'session'  => $session,
            'totals'   => $session->isLive() ? $this->totals->of($session) : null,
            'own'      => (int) $session->cashier_id === $this->userId(),
            'closes'   => $this->closes($session),
            'approves' => $session->status === PosSession::CLOSING && $this->access->can('pos.sessions.approve'),
            'reopens'  => $counted && $this->access->can('pos.sessions.reopen'),
            'denominations' => $session->status === PosSession::OPEN ? $this->posSettings->countingFor($workspace, $session->currency) : null,
        ];
    }

    /**
     * $data is validated (see ClosePosSessionRequest): the cash counted, the
     * variance's reason, the notes - and a supervisor's reason for closing
     * someone else's session. Counted by notes and coins, how many of each
     * instead. The session's page, as show() gives it.
     */
    public function close(Workspace $workspace, int $id, array $data): array
    {
        $session = $this->find($workspace, $id);
        $own     = (int) $session->cashier_id === $this->userId();
        $why     = trim((string) ($data['closer_reason'] ?? ''));

        if (! $own && ! $this->access->can('pos.sessions.approve')) {
            $this->refuse('Only its cashier, or someone who approves variances, closes a session.');
        }

        if (! $own && $why === '') {
            throw ValidationException::withMessages(['closer_reason' => 'Say why you close '.($session->cashier?->name ?? 'its cashier')."'s session."]);
        }

        $limit   = $this->settings->forWorkspace($workspace)->variance_limit;
        $data    = $this->byDenomination($data, $this->posSettings->countingFor($workspace, $session->currency), $session->currency);
        $closing = DB::transaction(function () use ($session, $data, $own, $why, $limit): PosSessionClosing {
            $locked = $this->sessions->lock($session);

            if ($locked->status !== PosSession::OPEN) {
                $this->refuse("Session {$locked->number} is closed already.");
            }

            // Phase F1: the cash expected is known once no cash movement waits for approval.
            if ($waiting = $this->movements->firstPendingIn($locked)) {
                $this->refuse("{$waiting->number} waits for approval: approve or decline it, then close the session.");
            }

            [$closing, $approved] = $this->counted($locked, $data, $limit === null ? null : (string) $limit);
            $now = now();

            return $this->sessions->close($locked, [
                ...$closing,
                'closer_reason' => $own ? null : $why,
                'closed_by'     => $this->userId(),
                'closed_at'     => $now,
                'approved_by'   => $closing['needs_approval'] && $approved ? $this->userId() : null,
                'approved_at'   => $closing['needs_approval'] && $approved ? $now : null,
            ], ['status' => $approved ? PosSession::CLOSED : PosSession::CLOSING, 'closed_at' => $now, 'closed_by' => $this->userId()]);
        });

        $whose    = $own ? '' : ' of '.($session->cashier?->name ?? 'a removed cashier');
        $variance = bccomp((string) $closing->variance, '0', 2) === 0 ? '' : " with a variance of {$session->currency} {$closing->variance}";
        $waits    = $closing->needs_approval && ! $closing->approved_at ? ', waiting for its approval' : '';

        ActivityLogService::record('updated', "Closed the POS session {$session->number}{$whose}{$variance}{$waits}".($own ? '' : ": {$why}"), $session, ['source' => 'api']);

        return $this->show($workspace, $session->id);
    }

    /** A variance beyond the limit approved: the session closes. The session's page, as show() gives it. */
    public function approve(Workspace $workspace, int $id): array
    {
        $session = $this->find($workspace, $id);

        DB::transaction(function () use ($session) {
            $locked = $this->sessions->lock($session);

            if ($locked->status !== PosSession::CLOSING) {
                $this->refuse("Session {$locked->number} has no variance waiting for approval.");
            }

            $this->sessions->approve($locked, $this->userId(), now());
        });

        $session = $this->find($workspace, $id);

        ActivityLogService::record('updated', "Approved the variance of {$session->currency} {$session->closing->variance} of the POS session {$session->number}", $session, ['source' => 'api']);

        return $this->show($workspace, $session->id);
    }

    /** $data is validated (see ReopenPosSessionRequest): why it opens again. The session's page, as show() gives it. */
    public function reopen(Workspace $workspace, int $id, array $data): array
    {
        $session = $this->find($workspace, $id);
        $reason  = trim($data['reason']);

        DB::transaction(function () use ($workspace, $session, $reason) {
            // Its register takes no other session until this one is open again.
            $register = $this->registers->lockFor($workspace, $session->pos_register_id);
            $locked   = $this->sessions->lock($session);

            if (! in_array($locked->status, PosSession::COUNTED, true)) {
                $this->refuse("Session {$locked->number} is open already.");
            }

            if ($register->status !== PosRegister::ACTIVE) {
                $this->refuse("The register {$register->name} is not active: switch it on to reopen its session.");
            }

            $other = $this->sessions->liveAt($register) ?? ($locked->cashier_id ? $this->sessions->liveOf($workspace, (int) $locked->cashier_id) : null);

            if ($other) {
                $this->refuse("Session {$other->number} is open, at {$other->register->name}: it is closed first.");
            }

            $this->sessions->reopen($locked, $this->userId(), now(), $reason);
        });

        ActivityLogService::record('updated', "Reopened the POS session {$session->number}: {$reason}", $session, ['source' => 'api']);

        return $this->show($workspace, $session->id);
    }

    /**
     * The session's X report while it is open - $kind 'x' - or, counted, its
     * Z report - 'z' - from its closing (Phase F3). Its file name and PDF.
     *
     * @return array{0: string, 1: string}
     */
    public function report(Workspace $workspace, int $id, string $kind): array
    {
        $session = $this->find($workspace, $id);
        $closing = $session->closing;

        if ($kind === 'x') {
            $session->isLive() || $this->refuse("Session {$session->number} is closed: its Z report shows how it closed.");
            $made = $this->documentPdf->posSessionReport($workspace, $session, $this->totals->of($session), null);
        } else {
            in_array($session->status, PosSession::COUNTED, true) && $closing && ! $closing->isReopened()
                || $this->refuse("Session {$session->number} is open: its X report shows it so far.");
            $made = $this->documentPdf->posSessionReport($workspace, $session, $closing->totals ?? [], $closing);
        }

        ActivityLogService::record('updated', 'Printed the '.strtoupper($kind)." report of the POS session {$session->number}", $session, ['source' => 'api']);

        return $made;
    }

    private function find(Workspace $workspace, int $id): PosSession
    {
        return $this->sessions->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'There is no such session.');
    }

    private function userId(): int
    {
        return (int) $this->access->user()->id;
    }

    /**
     * The closing's figures from the cash counted, and whether it closes at
     * once: its variance within the limit - $limit, null for none - or the
     * user one who approves variances.
     *
     * @return array{0: array, 1: bool}
     */
    private function counted(PosSession $session, array $data, ?string $limit): array
    {
        $totals   = $this->totals->of($session);
        $counted  = bcadd((string) $data['counted_cash'], '0', 2);
        $variance = bcsub($counted, $totals['expected_cash'], 2);
        $reason   = trim((string) ($data['variance_reason'] ?? ''));
        $differs  = bccomp($variance, '0', 2) !== 0;

        if ($differs && $reason === '') {
            throw ValidationException::withMessages(['variance_reason' => 'Say why the cash counted is not the cash expected.']);
        }

        $beyond = $limit !== null && bccomp(ltrim($variance, '-'), $limit, 2) === 1;

        return [[
            'opening_float'   => (string) $session->opening_float,
            'expected_cash'   => $totals['expected_cash'],
            'counted_cash'    => $counted,
            'denominations'   => $data['denominations'] ?? null,
            'variance'        => $variance,
            'variance_reason' => $differs ? $reason : null,
            'notes'           => trim((string) ($data['notes'] ?? '')) ?: null,
            'needs_approval'  => $beyond,
            'totals'          => Arr::except($totals, ['expected_cash']),
        ], ! $beyond || $this->access->can('pos.sessions.approve')];
    }

    /**
     * $data with the cash counted from how many of each of $values - the
     * notes and coins the session is counted by - and that count, as the Z
     * report keeps it: each one counted, the largest first. Counted as one
     * amount ($values null), $data as it is.
     */
    private function byDenomination(array $data, ?array $values, string $currency): array
    {
        if ($values === null) {
            return [...$data, 'denominations' => null];
        }

        if (! is_array($data['denominations'] ?? null)) {
            throw ValidationException::withMessages(['denominations' => 'Count the notes and coins: how many of each.']);
        }

        $counts = [];

        foreach ($data['denominations'] as $row) {
            $value = bcadd((string) $row['value'], '0', 2);

            if (! in_array($value, $values, true)) {
                throw ValidationException::withMessages(['denominations' => "{$currency} {$value} is not one of the notes and coins Settings → Point of Sale counts by."]);
            }

            $counts[$value] = ($counts[$value] ?? 0) + (int) $row['count'];
        }

        $rows  = [];
        $total = '0.00';

        foreach ($values as $value) {
            if (($counts[$value] ?? 0) > 0) {
                $amount = bcmul($value, (string) $counts[$value], 2);
                $rows[] = ['value' => $value, 'count' => $counts[$value], 'amount' => $amount];
                $total  = bcadd($total, $amount, 2);
            }
        }

        return [...$data, 'counted_cash' => $total, 'denominations' => $rows];
    }

    /** Whether the user closes it: open, and theirs with Open and Close Sessions - or anyone's, approving variances. */
    private function closes(PosSession $session): bool
    {
        if ($session->status !== PosSession::OPEN) {
            return false;
        }

        return (int) $session->cashier_id === $this->userId()
            ? $this->access->can(['pos.sessions.open', 'pos.sessions.approve'])
            : $this->access->can('pos.sessions.approve');
    }

    /** Why the user may not open a session on the register now - null when they may. */
    private function refusal(PosRegister $register, int $userId, bool $taken): ?string
    {
        return match (true) {
            $register->status === PosRegister::INACTIVE    => 'This register is switched off: it opens no sessions.',
            $register->status === PosRegister::MAINTENANCE => 'This register is under maintenance: it opens no sessions.',
            ! $register->cashiers->contains('id', $userId) => 'You are not one of this register\'s cashiers.',
            ! $this->access->usesLocation($register->location_id) => 'You may not use this register\'s location.',
            $taken                                         => 'This register has a session open.',
            default                                        => null,
        };
    }
}
