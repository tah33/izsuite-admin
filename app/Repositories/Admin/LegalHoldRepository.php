<?php

namespace App\Repositories\Admin;

use App\Models\Admin\LegalHold;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Legal holds on businesses (Doc 15 Phase G2). */
class LegalHoldRepository
{
    /** A page of them, those in force first, newest first; $filters: search (the business or the reference) and state (active or released). */
    public function paginated(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return LegalHold::query()
            ->with(['placer:id,first_name,last_name', 'releaser:id,first_name,last_name'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('business_name', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->when(preg_match('/^(?:BW-?)?0*(\d+)$/i', $search, $id), fn (Builder $byId) => $byId->orWhere('workspace_id', (int) $id[1]))))
            ->when(($filters['state'] ?? null) === 'active', fn (Builder $query) => $query->whereNull('released_at'))
            ->when(($filters['state'] ?? null) === 'released', fn (Builder $query) => $query->whereNotNull('released_at'))
            ->orderByRaw('case when released_at is null then 0 else 1 end')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    public function findOrFail(int $id): LegalHold
    {
        return LegalHold::with(['workspace', 'placer:id,first_name,last_name', 'releaser:id,first_name,last_name'])->findOrFail($id);
    }

    /** The holds in force on $workspace. */
    public function activeFor(Workspace $workspace): Collection
    {
        return LegalHold::active()->where('workspace_id', $workspace->id)->orderBy('placed_at')->get();
    }

    /** Whether a hold is in force on business $workspaceId. */
    public function isHeld(int $workspaceId): bool
    {
        return LegalHold::active()->where('workspace_id', $workspaceId)->exists();
    }

    public function create(array $data): LegalHold
    {
        return LegalHold::create($data);
    }

    public function update(LegalHold $hold, array $data): LegalHold
    {
        $hold->update($data);

        return $hold;
    }
}
