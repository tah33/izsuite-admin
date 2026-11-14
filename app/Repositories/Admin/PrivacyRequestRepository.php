<?php

namespace App\Repositories\Admin;

use App\Models\Admin\PrivacyRequest;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\User;
use App\Models\User\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Privacy requests (Doc 15 Phase G1). */
class PrivacyRequestRepository
{
    /**
     * A page of them, the open ones first by their due day, then the rest
     * newest first. $filters: search (the person asking, or PR- number),
     * type, and status - "open" for those still to answer.
     */
    public function paginated(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;

        return PrivacyRequest::query()
            ->with(['user:id,first_name,last_name,email', 'workspace:id,name'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $number = preg_match('/^(?:PR-?)?0*(\d+)$/i', $search, $match) ? (int) $match[1] : null;

                $query->where(fn (Builder $inner) => $inner
                    ->where('requester_name', 'like', "%{$search}%")
                    ->orWhere('requester_email', 'like', "%{$search}%")
                    ->when($number, fn (Builder $byNumber) => $byNumber->orWhere('id', $number)));
            })
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($status === 'open', fn (Builder $query) => $query->whereIn('status', [PrivacyRequest::RECEIVED, PrivacyRequest::VERIFIED, PrivacyRequest::IN_PROGRESS]))
            ->when($status && $status !== 'open', fn (Builder $query) => $query->where('status', $status))
            ->orderByRaw('case when status in (?, ?, ?) then 0 else 1 end', [PrivacyRequest::RECEIVED, PrivacyRequest::VERIFIED, PrivacyRequest::IN_PROGRESS])
            ->orderByRaw('case when status in (?, ?, ?) then due_on end', [PrivacyRequest::RECEIVED, PrivacyRequest::VERIFIED, PrivacyRequest::IN_PROGRESS])
            ->orderByDesc('id')
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    public function findOrFail(int $id): PrivacyRequest
    {
        return PrivacyRequest::with(['user', 'workspace', 'verifier:id,first_name,last_name', 'completer:id,first_name,last_name', 'creator:id,first_name,last_name'])->findOrFail($id);
    }

    public function create(array $data): PrivacyRequest
    {
        return PrivacyRequest::create($data);
    }

    public function update(PrivacyRequest $request, array $data): PrivacyRequest
    {
        $request->update($data);

        return $request;
    }

    /** Counts a download of $request's export. */
    public function countDownload(PrivacyRequest $request): void
    {
        $request->increment('export_downloads', 1, ['export_downloaded_at' => now()]);
    }

    /** The exports whose day has passed with their file still kept. */
    public function expiredExports(CarbonInterface $now): Collection
    {
        return PrivacyRequest::query()->whereNotNull('export_path')->where('export_expires_at', '<=', $now)->get();
    }

    /** The businesses $user owns. */
    public function ownedBusinesses(User $user): Collection
    {
        return $user->workspaces()->orderBy('id')->get(['id', 'name', 'status', 'user_id']);
    }

    /** Which of $businesses have a subscription still running: on a trial, paid, or in grace. */
    public function subscribed(Collection $businesses): Collection
    {
        return WorkspaceSubscription::query()
            ->whereIn('workspace_id', $businesses->pluck('id'))
            ->whereIn('status', WorkspaceSubscription::CURRENT)
            ->pluck('workspace_id')
            ->pipe(fn ($ids) => $businesses->whereIn('id', $ids->all())->values());
    }

    /** Whether $user is the owner or a member of $workspace. */
    public function belongsTo(User $user, Workspace $workspace): bool
    {
        return $workspace->user_id === $user->id || $workspace->members()->where('user_id', $user->id)->exists();
    }
}
