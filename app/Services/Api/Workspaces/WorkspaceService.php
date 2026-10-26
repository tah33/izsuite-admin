<?php

namespace App\Services\Api\Workspaces;

use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\UserStaffRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Files\FileStorage;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceService
{
    public function __construct(
        protected WorkspaceRepository $workspaceRepository,
        protected UserStaffRepository $userStaffRepository,
        protected FileStorage $fileStorage,
        protected SetUpNewWorkspace $setUpNewWorkspace,
    ) {}

    public function list(User $user): Collection
    {
        return $this->workspaceRepository->getForUser($user);
    }

    /**
     * A business added here starts the way registration starts the first one:
     * with its Main Location, and its owner on its staff. All of it is made
     * together or not at all, so a business never exists without them.
     */
    public function create(User $user, array $data): Workspace
    {
        return DB::transaction(function () use ($user, $data): Workspace {
            $workspace = $this->workspaceRepository->create($user, $data);

            // Each write is logged here, by name, so LogApiWriteActivity does not
            // add its own vaguer "POST api/v1/workspaces" entry for the same request.
            ActivityLogService::record('created', "Added workspace {$this->quoted($workspace)}", $workspace, ['source' => 'api']);

            $this->setUpNewWorkspace->handle($workspace, $user, 'api');

            return $workspace;
        });
    }

    public function update(User $user, int $id, array $data): Workspace
    {
        $workspace = $this->findOwned($user, $id);

        $this->workspaceRepository->update($workspace, $data);

        ActivityLogService::record('updated', "Updated workspace {$this->quoted($workspace)}", $workspace, [
            'source' => 'api',
            // nav_version goes up with a rename - bookkeeping, not something the user changed.
            'fields' => array_keys(array_diff_key($workspace->getChanges(), ['updated_at' => true, 'nav_version' => true])),
        ]);

        return $workspace;
    }

    public function delete(User $user, int $id): void
    {
        $workspace = $this->findOwned($user, $id);

        // The business's own data - locations, settings, staff, contacts, items,
        // payment accounts, the rows of its files - goes with it: the database
        // cascades. The stored files do not, so they are removed once the rows
        // are gone.
        $files = $workspace->businessFiles()->get();

        $this->workspaceRepository->delete($workspace);

        $this->fileStorage->forgetLater($files);

        // Staff of the owner's other businesses must not go on listing a workspace that is gone.
        $this->userStaffRepository->forgetWorkspace($user, $id);

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
