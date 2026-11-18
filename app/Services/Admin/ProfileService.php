<?php

namespace App\Services\Admin;

use App\Models\User\User;
use App\Repositories\Admin\AdminSignInRepository;
use App\Repositories\User\UserRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Hash;

/**
 * The signed-in admin's own account: their details, their password, their
 * two-factor sign-in and sign-in history (Doc 15 section 15.2), and deleting
 * the account.
 */
class ProfileService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected AdminSignInRepository $signIns,
        protected AdminSessionService $sessions,
        protected AdminTwoFactorService $twoFactor,
    ) {}

    /** What the profile page shows besides the account itself. */
    public function page(User $user): array
    {
        return [
            'user'              => $user,
            'twoFactorOn'       => $this->twoFactor->isOn($user),
            'recoveryCodesLeft' => $this->twoFactor->recoveryCodesLeft($user),
            'signIns'           => $this->signIns->latestFor($user),
        ];
    }

    public function update(User $user, array $data): void
    {
        $this->userRepository->update($user, $data);

        ActivityLogService::record('updated', 'Updated their own profile', $user);
    }

    /** A new password. Every other session of the account ends; this one goes on. */
    public function changePassword(Session $session, User $user, string $password): void
    {
        $this->userRepository->update($user, [
            'password' => Hash::make($password),
        ]);

        $this->sessions->endAllBut($session, $user);

        ActivityLogService::record('updated', 'Changed their own password, ending their other sessions', $user, ['changes' => []]);
    }

    /** Why $user cannot delete their own account, if they cannot. */
    public function deletionProblem(User $user): ?string
    {
        return $user->isSuperAdmin() ? __('The super admin account cannot be deleted.') : null;
    }

    public function delete(User $user): void
    {
        ActivityLogService::record('deleted', 'Deleted their own admin account', $user, null, $user);

        $this->userRepository->delete($user);
    }
}
