<?php

namespace App\Services\Api\Pos;

use App\Models\User\PosSession;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\PosApproverRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A manager's approval at a cashier's register (Doc 18 section 18.31 -
 * Phase I2): what the cashier may not do alone - a discount beyond the
 * limit, a price typed over, cash moved above the limit, a refund by another
 * method - approved there by someone who may, with their PIN. The approval is
 * a token the cashier's requests carry, good for that register's session and
 * that one kind of thing for ten minutes; what it allowed names its approver.
 */
class PosApprovals
{
    use RefusesConflicts;

    /** Each kind of approval: the permission it takes, and what it is called. */
    public const ACTIONS = [
        'beyond'   => ['pos.selling.beyond', 'a discount beyond the limit'],
        'override' => ['pos.selling.override', 'a price typed over'],
        'cash'     => ['pos.cash.approve', 'cash moved above the limit'],
        'refund'   => ['pos.returns.alternative', 'a refund by another method'],
    ];

    private const MINUTES = 10;

    public function __construct(
        protected PosCounter $counter,
        protected PosApproverRepository $approvers,
        protected WorkspaceAccess $access,
    ) {}

    /** Who may approve $action at the user's register: holding what it takes, with a PIN, at its location - not the cashier. */
    public function approvers(Workspace $workspace, string $action): Collection
    {
        $session = $this->own($workspace);

        return $this->approvers->candidates($workspace)
            ->reject(fn (User $user) => $user->id === (int) $this->access->user()->id)
            ->filter(fn (User $user) => $this->may($workspace, $session, $user, $action))
            ->values();
    }

    /**
     * $approverId's approval of $action at the user's register, by their
     * PIN: its token, and who approved.
     *
     * @return array{token: string, approver: User, action: string}
     */
    public function approve(Workspace $workspace, int $approverId, string $pin, string $action): array
    {
        $session  = $this->own($workspace);
        $approver = $this->approvers($workspace, $action)->firstWhere('id', $approverId)
            ?? throw ValidationException::withMessages(['approver_id' => 'Choose someone who may approve this at this register.']);

        if (! Hash::check($pin, (string) $approver->pos_pin)) {
            throw ValidationException::withMessages(['pin' => 'That PIN is not right.']);
        }

        $token = Str::random(40);
        Cache::put($this->key($token), ['workspace' => $workspace->id, 'session' => $session->id, 'action' => $action, 'approver' => $approver->id], now()->addMinutes(self::MINUTES));

        ActivityLogService::record('updated', "{$approver->name} approved ".self::ACTIONS[$action][1]." at {$session->register->name} ({$session->register->code}), in session {$session->number}", $session, ['source' => 'api']);

        return ['token' => $token, 'approver' => $approver, 'action' => $action];
    }

    /** Who approved $action by $token at the user's register - none without one, or for one not good here now. */
    public function approverFor(Workspace $workspace, string $action, ?string $token): ?User
    {
        $approval = $token ? Cache::get($this->key($token)) : null;
        $session  = is_array($approval) ? $this->counter->session($workspace) : null;

        if (! $session || $approval['workspace'] !== $workspace->id || $approval['session'] !== $session->id || $approval['action'] !== $action) {
            return null;
        }

        $approver = $this->approvers->find((int) $approval['approver']);

        return $approver && $this->may($workspace, $session, $approver, $action) ? $approver : null;
    }

    /** Whether $user holds what approving $action takes, at the register's location. */
    private function may(Workspace $workspace, PosSession $session, User $user, string $action): bool
    {
        $locations = $this->access->locationsOf($user, $workspace);

        return $this->access->allows($user, $workspace, self::ACTIONS[$action][0])
            && ($locations === null || in_array((int) $session->register->location_id, $locations, true));
    }

    private function own(Workspace $workspace): PosSession
    {
        return $this->counter->session($workspace) ?? $this->refuse('Open your session first: a manager approves at your register.');
    }

    private function key(string $token): string
    {
        return 'pos-approval:'.hash('sha256', $token);
    }
}
