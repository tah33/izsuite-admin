<?php

namespace App\Services\Api\Pos;

use App\Models\User\PosSession;
use App\Models\User\Workspace;
use App\Repositories\User\PosPinRepository;
use App\Repositories\User\PosSessionRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * A cashier's PIN and their register's lock (Doc 18 section 18.31 - Phase
 * I1). Each user sets their own PIN, 4 to 6 digits, once their account's
 * password is right; it is kept hashed and never shown. Their register locks
 * when they say, or after Settings → Point of Sale's minutes without use
 * (PosCounter), and opens again with their PIN - or, until they have one,
 * their password. While it is locked nothing is sold at it.
 */
class PosLockService
{
    use RefusesConflicts;

    public function __construct(
        protected PosCounter $counter,
        protected PosPinRepository $pins,
        protected PosSessionRepository $sessions,
        protected WorkspaceAccess $access,
    ) {}

    /** Whether the signed-in user has a PIN. */
    public function hasPin(): bool
    {
        return $this->pins->pinOf($this->access->user()) !== null;
    }

    /** Sets - or changes - the signed-in user's PIN, their password said again. */
    public function pin(Workspace $workspace, string $pin, string $password): void
    {
        $user = $this->access->user();

        if (! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages(['password' => 'That is not your password.']);
        }

        $had = $this->pins->pinOf($user) !== null;
        $this->pins->save($user, Hash::make($pin));

        ActivityLogService::record('updated', $had ? 'Changed their POS PIN' : 'Set their POS PIN', $workspace, ['source' => 'api']);
    }

    /** Locks the register of the user's own open session. */
    public function lock(Workspace $workspace): PosSession
    {
        $session = $this->own($workspace);

        if (! $session->locked_at) {
            $this->sessions->mark($session, ['locked_at' => now()]);
        }

        return $session;
    }

    /** Opens it again: with the user's PIN, or their password while they have none. */
    public function unlock(Workspace $workspace, ?string $pin, ?string $password): PosSession
    {
        $session = $this->own($workspace);
        $user    = $this->access->user();
        $hash    = $this->pins->pinOf($user);

        if ($hash ? ! ($pin !== null && Hash::check($pin, $hash)) : ! ($password !== null && Hash::check($password, (string) $user->password))) {
            throw ValidationException::withMessages($hash ? ['pin' => 'That PIN is not right.'] : ['password' => 'That is not your password.']);
        }

        $this->sessions->mark($session, ['locked_at' => null, 'last_active_at' => now()]);

        return $session;
    }

    private function own(Workspace $workspace): PosSession
    {
        return $this->counter->session($workspace) ?? $this->refuse('Open your session first: a register locks for its cashier.');
    }
}
