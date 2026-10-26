<?php

namespace App\Services\Admin;

use App\Models\User\User;
use App\Repositories\User\UserRepository;
use Illuminate\Support\Facades\Hash;

/**
 * The signed-in admin's own account: their details, their password, and
 * deleting the account.
 */
class ProfileService
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    public function update(User $user, array $data): void
    {
        $this->userRepository->update($user, $data);
    }

    public function changePassword(User $user, string $password): void
    {
        $this->userRepository->update($user, [
            'password' => Hash::make($password),
        ]);
    }

    /** Why $user cannot delete their own account, if they cannot. */
    public function deletionProblem(User $user): ?string
    {
        return $user->isSuperAdmin() ? __('The super admin account cannot be deleted.') : null;
    }

    public function delete(User $user): void
    {
        $this->userRepository->delete($user);
    }
}
