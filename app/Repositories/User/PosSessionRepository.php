<?php

namespace App\Repositories\User;

use App\Models\User\PosRegister;
use App\Models\User\PosSession;
use App\Models\User\PosSessionClosing;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** A business's POS sessions (Doc 18 Phase B1), each seen only where its register's location may be used. */
class PosSessionRepository
{
    /** What a session's row shows. */
    private const LISTED_WITH = ['register:id,name,code,location_id', 'cashier:id,first_name,last_name', 'closing'];

    /** What a session's page shows besides. */
    private const SHOWN_WITH = ['register.location:id,name', 'closer:id,first_name,last_name', 'closing.closer:id,first_name,last_name'];

    /** A page of its sessions at $locationIds - every one, for null - the latest opened first, by status and register. */
    public function paginateFor(Workspace $workspace, ?array $locationIds, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->at($workspace, $locationIds)
            ->with(self::LISTED_WITH)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['register_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('pos_register_id', (int) $id))
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findFor(Workspace $workspace, int $id, ?array $locationIds): ?PosSession
    {
        return $this->at($workspace, $locationIds)->with([...self::LISTED_WITH, ...self::SHOWN_WITH])->find($id);
    }

    /** The session as it stands, locked while it changes: inside a transaction. */
    public function lock(PosSession $session): PosSession
    {
        return PosSession::query()->lockForUpdate()->findOrFail($session->id);
    }

    /** The register's session not yet closed, if any. */
    public function liveAt(PosRegister $register): ?PosSession
    {
        return PosSession::query()->where('pos_register_id', $register->id)->whereIn('status', PosSession::LIVE)->first();
    }

    /** The cashier's session not yet closed in the business, if any, with its register. */
    public function liveOf(Workspace $workspace, int $userId): ?PosSession
    {
        return PosSession::query()->where('workspace_id', $workspace->id)->where('cashier_id', $userId)
            ->whereIn('status', PosSession::LIVE)->with('register:id,name,code')->first();
    }

    /** The ids of its registers with a session not yet closed. */
    public function liveRegisterIds(Workspace $workspace): array
    {
        return PosSession::query()->where('workspace_id', $workspace->id)->whereIn('status', PosSession::LIVE)
            ->pluck('pos_register_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Whether any session was ever held at the register. */
    public function heldAt(PosRegister $register): bool
    {
        return PosSession::query()->where('pos_register_id', $register->id)->exists();
    }

    public function open(Workspace $workspace, PosRegister $register, array $data): PosSession
    {
        $session = new PosSession($data);
        $session->workspace_id = $workspace->id;
        $session->pos_register_id = $register->id;
        $session->save();

        return $session;
    }

    /** Its Z report kept, and the session changed as $changes say. */
    public function close(PosSession $session, array $closing, array $changes): PosSessionClosing
    {
        $record = $session->closings()->create($closing);
        $session->forceFill($changes)->save();

        return $record;
    }

    private function at(Workspace $workspace, ?array $locationIds): Builder
    {
        return PosSession::query()->where('pos_sessions.workspace_id', $workspace->id)
            ->when($locationIds !== null, fn (Builder $query) => $query->whereHas('register', fn (Builder $register) => $register->whereIn('location_id', $locationIds)));
    }
}
