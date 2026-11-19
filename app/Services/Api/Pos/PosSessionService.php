<?php

namespace App\Services\Api\Pos;

use App\Models\User\PosRegister;
use App\Models\User\PosSession;
use App\Models\User\Workspace;
use App\Repositories\User\PosRegisterRepository;
use App\Repositories\User\PosSessionRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * POS sessions (Doc 18 sections 18.6, 18.27 and 18.28 - Phase B1).
 *
 * A cashier opens one on an active register they are a cashier of, at a
 * location they may use, with the opening cash float - a float, not income -
 * and notes: one session not yet closed per register, and per cashier. Its
 * cashier closes it with the cash counted. The cash expected comes from
 * PosSessionTotals, and the variance - counted less expected - needs a
 * reason when it is not nil. The closing is kept as the session's Z report,
 * never changed, and the register is free for the next session.
 *
 * Sessions are seen where their register's location may be used. Each
 * opening and closing is logged. A supervisor's closing of someone else's
 * session, approving variances and reopening come with Phase B2.
 */
class PosSessionService
{
    use RefusesConflicts;

    public function __construct(
        protected PosSessionRepository $sessions,
        protected PosRegisterRepository $registers,
        protected PosSessionTotals $totals,
        protected WorkspaceAccess $access,
    ) {}

    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->sessions->paginateFor($workspace, $this->access->locations(), $filters, $perPage);
    }

    /**
     * The Sessions page's choices: the registers it can be filtered by, each
     * with why the user may not open a session on it now - null when they
     * may - and the user's own session not yet closed, if any.
     *
     * @return array{registers: list<array{register: PosRegister, refusal: ?string}>, mine: ?PosSession}
     */
    public function options(Workspace $workspace): array
    {
        $userId = (int) $this->access->user()->id;
        $live   = $this->sessions->liveRegisterIds($workspace);

        return [
            'registers' => $this->registers->getFor($workspace, $this->access->locations())
                ->map(fn (PosRegister $register) => ['register' => $register, 'refusal' => $this->refusal($register, $userId, in_array($register->id, $live, true))])
                ->all(),
            'mine'      => $this->sessions->liveOf($workspace, $userId),
        ];
    }

    /** $data is validated (see OpenPosSessionRequest). The new session's page, as show() gives it. */
    public function open(Workspace $workspace, array $data): array
    {
        $userId = (int) $this->access->user()->id;

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
     * A session's page: it, what it has taken so far while it is not closed
     * (its Z report says it once it is), and whether the user closes it.
     *
     * @return array{session: PosSession, totals: ?array, closes: bool}
     */
    public function show(Workspace $workspace, int $id): array
    {
        $session = $this->find($workspace, $id);

        return [
            'session' => $session,
            'totals'  => $session->isLive() ? $this->totals->of($session) : null,
            'closes'  => $this->closes($session),
        ];
    }

    /** $data is validated (see ClosePosSessionRequest): the cash counted, the variance's reason and the notes. The session's page, as show() gives it. */
    public function close(Workspace $workspace, int $id, array $data): array
    {
        $session = $this->find($workspace, $id);

        if ((int) $session->cashier_id !== (int) $this->access->user()->id) {
            $this->refuse('Only its cashier closes a session.');
        }

        $closing = DB::transaction(function () use ($session, $data) {
            $locked = $this->sessions->lock($session);

            if ($locked->status !== PosSession::OPEN) {
                $this->refuse("Session {$locked->number} is closed already.");
            }

            $totals   = $this->totals->of($locked);
            $counted  = bcadd((string) $data['counted_cash'], '0', 2);
            $variance = bcsub($counted, $totals['expected_cash'], 2);
            $reason   = trim((string) ($data['variance_reason'] ?? ''));
            $differs  = bccomp($variance, '0', 2) !== 0;

            if ($differs && $reason === '') {
                throw ValidationException::withMessages(['variance_reason' => 'Say why the cash counted is not the cash expected.']);
            }

            $now    = now();
            $userId = (int) $this->access->user()->id;

            return $this->sessions->close($locked, [
                'opening_float'   => (string) $locked->opening_float,
                'expected_cash'   => $totals['expected_cash'],
                'counted_cash'    => $counted,
                'variance'        => $variance,
                'variance_reason' => $differs ? $reason : null,
                'notes'           => trim((string) ($data['notes'] ?? '')) ?: null,
                'totals'          => Arr::except($totals, ['expected_cash']),
                'closed_by'       => $userId,
                'closed_at'       => $now,
            ], ['status' => PosSession::CLOSED, 'closed_at' => $now, 'closed_by' => $userId]);
        });

        $variance = bccomp((string) $closing->variance, '0', 2) === 0 ? '' : " with a variance of {$session->currency} {$closing->variance}";

        ActivityLogService::record('updated', "Closed the POS session {$session->number}{$variance}", $session, ['source' => 'api']);

        return $this->show($workspace, $session->id);
    }

    private function find(Workspace $workspace, int $id): PosSession
    {
        return $this->sessions->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'There is no such session.');
    }

    /** Whether the user closes it: their own, open, with Open and Close Sessions. */
    private function closes(PosSession $session): bool
    {
        return $session->status === PosSession::OPEN
            && (int) $session->cashier_id === (int) $this->access->user()->id
            && $this->access->can('pos.sessions.open');
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
