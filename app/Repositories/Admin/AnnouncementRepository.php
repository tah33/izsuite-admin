<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Announcement;
use App\Models\Admin\AnnouncementDismissal;
use App\Models\User\User;
use App\Models\User\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Platform announcements and who has dismissed them (Doc 15 Phase F1). */
class AnnouncementRepository
{
    /** A page of them, newest first: by title, and by state at $now. */
    public function page(array $filters, CarbonInterface $now, int $perPage): LengthAwarePaginator
    {
        return Announcement::query()
            ->with('creator:id,first_name,last_name')
            ->withCount('dismissals')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($filters['state'] ?? null, fn (Builder $query, string $state) => match ($state) {
                'scheduled' => $query->where('starts_at', '>', $now),
                'ended'     => $query->where('ends_at', '<=', $now),
                default     => $this->live($query, $now),
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** The announcements live at $now - for businesses, or for izSuite's admins. */
    public function liveAt(CarbonInterface $now, bool $forAdmins): Collection
    {
        return $this->live(Announcement::query(), $now)
            ->when($forAdmins, fn (Builder $query) => $query->where('audience', Announcement::ADMINS), fn (Builder $query) => $query->where('audience', '!=', Announcement::ADMINS))
            ->latest('id')
            ->get();
    }

    public function find(int $id): ?Announcement
    {
        return Announcement::find($id);
    }

    public function create(array $data): Announcement
    {
        return Announcement::create($data);
    }

    public function update(Announcement $announcement, array $data): Announcement
    {
        $announcement->update($data);

        return $announcement;
    }

    public function delete(Announcement $announcement): void
    {
        $announcement->delete();
    }

    /** @return list<int> those of $ids $user has dismissed */
    public function dismissedBy(User $user, array $ids): array
    {
        return AnnouncementDismissal::query()->where('user_id', $user->id)->whereIn('announcement_id', $ids)->pluck('announcement_id')->all();
    }

    public function dismiss(Announcement $announcement, User $user): void
    {
        AnnouncementDismissal::firstOrCreate(['announcement_id' => $announcement->id, 'user_id' => $user->id]);
    }

    /** @return list<int> those of $ids that are businesses */
    public function businessIds(array $ids): array
    {
        return Workspace::whereIn('id', $ids)->pluck('id')->all();
    }

    /** @return array<int, string> $ids' businesses by ID, as BW- IDs and names */
    public function businessLabels(array $ids): array
    {
        return Workspace::whereIn('id', $ids)->get(['id', 'name'])->mapWithKeys(fn (Workspace $workspace) => [$workspace->id => $workspace->businessId().' '.$workspace->name])->all();
    }

    private function live(Builder $query, CarbonInterface $now): Builder
    {
        return $query
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }
}
