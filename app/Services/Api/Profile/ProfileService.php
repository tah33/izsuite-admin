<?php

namespace App\Services\Api\Profile;

use App\Models\User\User;
use App\Repositories\User\UserRepository;
use App\Services\Shared\ActivityLogService;

class ProfileService
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    /**
     * Save the editable profile fields of the signed-in account.
     *
     * $data is the validated request, so it can only ever hold the fields
     * UpdateProfileRequest allows - role, status and the rest stay out of reach
     * even though they are mass assignable.
     */
    public function update(User $user, array $data): User
    {
        $updated = $this->userRepository->update($user, $data);

        // Logged even when nothing changed: LogApiWriteActivity would otherwise
        // write its own, vaguer entry for the successful PUT. `fields` is what
        // tells the two cases apart.
        ActivityLogService::record('updated', 'Updated their profile', $updated, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($user->getChanges(), ['updated_at' => true])),
        ]);

        return $updated;
    }
}
