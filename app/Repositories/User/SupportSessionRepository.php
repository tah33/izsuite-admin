<?php

namespace App\Repositories\User;

use App\Models\User\SupportSession;
use App\Models\User\Ticket;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;

/** Support sessions (Doc 15 section 15.22), and the API tokens that carry them. */
class SupportSessionRepository
{
    public function create(array $data): SupportSession
    {
        return SupportSession::create($data);
    }

    public function find(int $id): ?SupportSession
    {
        return SupportSession::with(['workspace:id,user_id,name', 'ticket:id,subject'])->find($id);
    }

    public function findWorkspace(int $id): ?Workspace
    {
        return Workspace::find($id, ['id', 'user_id', 'name']);
    }

    public function update(SupportSession $session, array $data): SupportSession
    {
        $session->update($data);

        return $session;
    }

    /** The business's sessions still open, then its latest ended ones. */
    public function recentFor(Workspace $workspace, int $limit = 5): Collection
    {
        return $workspace->supportSessions()
            ->with('ticket:id,subject')
            ->orderByRaw('ended_at is null desc')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /** Its cases a session can be for: any not closed, newest activity first. */
    public function casesFor(Workspace $workspace): Collection
    {
        return $workspace->supportCases()
            ->where('status', '!=', Ticket::CLOSED)
            ->latest('updated_at')
            ->get(['id', 'workspace_id', 'subject', 'status']);
    }

    public function isOpenCaseOf(Workspace $workspace, int $ticketId): bool
    {
        return $workspace->supportCases()->whereKey($ticketId)->where('status', '!=', Ticket::CLOSED)->exists();
    }

    /** Admin > Support Sessions: newest first, by state (live, ended), access and business. */
    public function page(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return SupportSession::query()
            ->with(['workspace:id,name', 'ticket:id,subject'])
            ->when(($filters['state'] ?? null) === 'live', fn (Builder $query) => $query->whereNull('ended_at')->where('expires_at', '>', now()))
            ->when(($filters['state'] ?? null) === 'ended', fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNotNull('ended_at')->orWhere('expires_at', '<=', now())))
            ->when($filters['access'] ?? null, fn (Builder $query, string $access) => $query->where('access', $access))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->whereHas('workspace', fn (Builder $business) => $business->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
                ->orWhere('admin_name', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->latest('id')
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    /** @return Collection<int, SupportSession> those past their end that nothing has ended yet */
    public function expiredUnended(): Collection
    {
        return SupportSession::with('workspace:id,user_id,name')->whereNull('ended_at')->where('expires_at', '<=', now())->get();
    }

    public function revokeTokens(SupportSession $session): int
    {
        return PersonalAccessToken::where('name', $session->tokenName())->delete();
    }
}
