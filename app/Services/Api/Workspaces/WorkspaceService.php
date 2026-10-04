<?php

namespace App\Services\Api\Workspaces;

use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class WorkspaceService
{
    public function __construct(
        protected WorkspaceRepository $workspaceRepository,
    ) {}

    public function list(User $user): Collection
    {
        return $this->workspaceRepository->getForUser($user);
    }

    public function create(User $user, array $data): Workspace
    {
        $workspace = $this->workspaceRepository->create($user, $data);

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer "POST api/v1/workspaces" entry for the same request.
        ActivityLogService::record('created', "Added workspace {$this->quoted($workspace)}", $workspace, ['source' => 'api']);

        return $workspace;
    }

    public function update(User $user, int $id, array $data): Workspace
    {
        $workspace = $this->findOwned($user, $id);

        $this->workspaceRepository->update($workspace, $data);

        ActivityLogService::record('updated', "Updated workspace {$this->quoted($workspace)}", $workspace, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($workspace->getChanges(), ['updated_at' => true])),
        ]);

        return $workspace;
    }

    public function delete(User $user, int $id): void
    {
        $workspace = $this->findOwned($user, $id);

        $this->workspaceRepository->delete($workspace);

        ActivityLogService::record('deleted', "Deleted workspace {$this->quoted($workspace)}", $workspace, ['source' => 'api']);
    }

    /**
     * A workspace that is someone else's answers exactly like one that does not
     * exist. Aborting with our own message also keeps Laravel's default out of
     * the response - "No query results for model [App\Models\User\Workspace]" -
     * which names an internal class.
     */
    private function findOwned(User $user, int $id): Workspace
    {
        return $this->workspaceRepository->findForUser($user, $id)
            ?? abort(404, 'Workspace not found.');
    }

    /**
     * The name, in quotes, for an activity log description.
     *
     * activity_logs.description is a 255-character column and a workspace name
     * may be as long as 255 itself, so the name is cut down: left whole, a
     * valid long name would make the log insert fail after the workspace was
     * already saved, and the request would answer 500 for a write that worked.
     */
    private function quoted(Workspace $workspace): string
    {
        return '"'.Str::limit($workspace->name, 100).'"';
    }
}
