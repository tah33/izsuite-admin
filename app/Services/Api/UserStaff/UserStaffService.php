<?php

namespace App\Services\Api\UserStaff;

use App\Models\User\User;
use App\Models\User\UserStaff;
use App\Repositories\User\UserStaffRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class UserStaffService
{
    public function __construct(
        protected UserStaffRepository $userStaffRepository,
    ) {}

    public function list(User $user): Collection
    {
        return $this->userStaffRepository->getForUser($user);
    }

    public function create(User $user, array $data): UserStaff
    {
        $staff = $this->userStaffRepository->create($user, $data);

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer "POST api/v1/user-staff" entry for the same request.
        ActivityLogService::record('created', "Added staff member {$this->quoted($staff)}", $staff, ['source' => 'api']);

        return $staff;
    }

    public function update(User $user, int $id, array $data): UserStaff
    {
        $staff = $this->findOwned($user, $id);

        $this->userStaffRepository->update($staff, $data);

        ActivityLogService::record('updated', "Updated staff member {$this->quoted($staff)}", $staff, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($staff->getChanges(), ['updated_at' => true])),
        ]);

        return $staff;
    }

    public function delete(User $user, int $id): void
    {
        $staff = $this->findOwned($user, $id);

        $this->userStaffRepository->delete($staff);

        ActivityLogService::record('deleted', "Deleted staff member {$this->quoted($staff)}", $staff, ['source' => 'api']);
    }

    /**
     * A staff member that is someone else's answers exactly like one that does
     * not exist. Aborting with our own message also keeps Laravel's default out
     * of the response - "No query results for model [App\Models\User\UserStaff]" -
     * which names an internal class.
     */
    private function findOwned(User $user, int $id): UserStaff
    {
        return $this->userStaffRepository->findForUser($user, $id)
            ?? abort(404, 'Staff member not found.');
    }

    /**
     * The name, in quotes, for an activity log description.
     *
     * activity_logs.description is a 255-character column and a name may be as
     * long as 255 itself, so the name is cut down: left whole, a valid long name
     * would make the log insert fail after the staff member was already saved,
     * and the request would answer 500 for a write that worked.
     */
    private function quoted(UserStaff $staff): string
    {
        return '"'.Str::limit($staff->name, 100).'"';
    }
}
