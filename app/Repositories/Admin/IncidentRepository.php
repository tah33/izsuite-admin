<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Incident;
use App\Models\Admin\IncidentNote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Incidents and their notes (Doc 15 section 15.23 - Phase D3b). */
class IncidentRepository
{
    /**
     * A page of incidents: those not resolved first, then the newest.
     * $filters: status (a status, or "unresolved"), service, severity and
     * search (the title).
     */
    public function page(array $filters, int $perPage): LengthAwarePaginator
    {
        return Incident::with('responsible:id,first_name,last_name')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $status === 'unresolved'
                ? $query->whereIn('status', Incident::UNRESOLVED)
                : $query->where('status', $status))
            ->when($filters['service'] ?? null, fn (Builder $query, string $service) => $query->where('service', $service))
            ->when($filters['severity'] ?? null, fn (Builder $query, string $severity) => $query->where('severity', $severity))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderByRaw('case when status = ? then 1 else 0 end', [Incident::RESOLVED])
            ->latest('started_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return Collection<int, Incident> the incidents not resolved yet, the most severe and then the oldest first */
    public function unresolved(): Collection
    {
        return Incident::with('responsible:id,first_name,last_name')
            ->whereIn('status', Incident::UNRESOLVED)
            ->orderByRaw("case severity when 'critical' then 0 when 'major' then 1 else 2 end")
            ->oldest('started_at')
            ->get();
    }

    /** One incident with who made it and is responsible, and its notes newest first with their authors. */
    public function findForPage(int $id): ?Incident
    {
        return Incident::with([
            'responsible:id,first_name,last_name', 'creator:id,first_name,last_name',
            'notes' => fn ($notes) => $notes->with('author:id,first_name,last_name')->latest('id'),
        ])->find($id);
    }

    public function find(int $id): ?Incident
    {
        return Incident::find($id);
    }

    public function create(array $data): Incident
    {
        return Incident::create($data);
    }

    public function update(Incident $incident, array $data): Incident
    {
        $incident->update($data);

        return $incident;
    }

    public function addNote(Incident $incident, array $data): IncidentNote
    {
        return $incident->notes()->create($data);
    }
}
