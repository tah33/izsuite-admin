<?php

namespace App\Services\Api\Sales;

use App\Models\User\SalesTeam;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\SalesTeamRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sales teams (Doc 16 Phase A, Settings → Sales; and Doc 17 Phase A2,
 * Settings → CRM): a name, its members and manager - the business's active
 * people - and whether it is in use. A team named on a document is switched
 * off rather than deleted, so the document keeps it. The sales form chooses
 * a salesperson among the active members and a team among those switched on
 * (options()).
 */
class SalesTeamService
{
    public function __construct(
        protected SalesTeamRepository $teams,
    ) {}

    public function index(Workspace $workspace): Collection
    {
        return $this->teams->forWorkspace($workspace);
    }

    /** @return array{people: \Illuminate\Support\Collection, teams: Collection} what the sales form chooses from */
    public function options(Workspace $workspace): array
    {
        return ['people' => $this->teams->salespeople($workspace), 'teams' => $this->teams->forWorkspace($workspace, activeOnly: true)];
    }

    /** A team added or changed. $data is validated (SaveSalesTeamRequest): name, is_active, member_ids. */
    public function save(Workspace $workspace, ?int $id, array $data): SalesTeam
    {
        $team = $id ? ($this->teams->find($workspace, $id) ?? abort(404, 'Sales team not found.')) : null;
        $name = trim($data['name']);

        if ($this->teams->nameTaken($workspace, $name, $team?->id)) {
            throw ValidationException::withMessages(['name' => 'Another sales team has this name.']);
        }

        $memberIds = array_values(array_unique(array_map('intval', $data['member_ids'] ?? [])));

        if (count($this->teams->activeMemberIds($workspace, $memberIds)) !== count($memberIds)) {
            throw ValidationException::withMessages(['member_ids' => 'Choose members of this business who are active.']);
        }

        $fields = ['name' => $name, 'is_active' => (bool) ($data['is_active'] ?? true)];

        // Its manager (Doc 17 Phase A2): one of the business's active people - changed only when sent.
        if (array_key_exists('manager_id', $data)) {
            $managerId = $data['manager_id'] === null ? null : (int) $data['manager_id'];

            if ($managerId !== null && $this->teams->activeMemberIds($workspace, [$managerId]) === []) {
                throw ValidationException::withMessages(['manager_id' => 'Choose a member of this business who is active.']);
            }

            $fields['manager_id'] = $managerId;
        }

        $saved = DB::transaction(fn () => $team ? $this->teams->update($team, $fields, $memberIds) : $this->teams->create($workspace, $fields, $memberIds));

        ActivityLogService::record($team ? 'updated' : 'created', ($team ? 'Updated' : 'Added')." sales team \"{$saved->name}\"", $workspace, ['source' => 'api', 'members' => count($memberIds)]);

        return $saved->load(['members:id,first_name,last_name', 'manager:id,first_name,last_name'])->loadCount('documents');
    }

    /** The people a team can have as its members and manager (Doc 17 Phase A2): the business's owner and active members, by name. */
    public function people(Workspace $workspace): \Illuminate\Support\Collection
    {
        return $this->teams->salespeople($workspace);
    }

    /** A team no document names, deleted: one that is named is switched off instead. */
    public function delete(Workspace $workspace, int $id, User $user): void
    {
        $team = $this->teams->find($workspace, $id) ?? abort(404, 'Sales team not found.');

        if ($this->teams->isUsed($team)) {
            throw ValidationException::withMessages(['team' => 'Sales documents or leads name this team, so it stays: switch it off instead.']);
        }

        $this->teams->delete($team);

        ActivityLogService::record('deleted', "Deleted sales team \"{$team->name}\"", $workspace, ['source' => 'api'], $user);
    }
}
