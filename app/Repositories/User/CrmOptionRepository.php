<?php

namespace App\Repositories\User;

use App\Models\User\CrmOption;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/** A business's CRM lists (Doc 17 Phase A1): its lead sources, lost reasons and activity types. */
class CrmOptionRepository
{
    /** Every list's entries, each list in its order. */
    public function getFor(Workspace $workspace): Collection
    {
        return $workspace->crmOptions()->orderBy('kind')->orderBy('position')->orderBy('id')->get();
    }

    public function existsFor(Workspace $workspace): bool
    {
        return $workspace->crmOptions()->exists();
    }

    /**
     * Every list's starting entries (CrmOption::STARTER) in their order. One
     * made already by a request alongside - by its key - is left as it is.
     */
    public function addStarter(Workspace $workspace): void
    {
        $now  = now();
        $rows = [];

        foreach (CrmOption::STARTER as $kind => $names) {
            foreach (array_keys($names) as $index => $key) {
                $rows[] = [
                    'workspace_id' => $workspace->id, 'kind' => $kind, 'key' => $key, 'name' => $names[$key], 'position' => $index + 1, 'is_active' => true,
                    // Doc 17 Phase E1: the activity types worth an outcome once done.
                    'needs_outcome' => $kind === 'activity_type' && in_array($key, CrmOption::NEEDS_OUTCOME, true),
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }

        CrmOption::query()->insertOrIgnore($rows);
    }

    public function findFor(Workspace $workspace, int $id): ?CrmOption
    {
        return $workspace->crmOptions()->find($id);
    }

    /** Added at the end of its list. */
    public function create(Workspace $workspace, array $data): CrmOption
    {
        $last = (int) $workspace->crmOptions()->where('kind', $data['kind'])->max('position');

        return $workspace->crmOptions()->create([...$data, 'position' => $last + 1]);
    }

    public function update(CrmOption $option, array $data): CrmOption
    {
        $option->update($data);

        return $option;
    }

    public function delete(CrmOption $option): void
    {
        $option->delete();
    }
}
