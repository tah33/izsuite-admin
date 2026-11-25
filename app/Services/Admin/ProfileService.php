<?php

namespace App\Services\Admin;

use App\Models\User\User;
use App\Repositories\Admin\AdminSignInRepository;
use App\Repositories\User\UserRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\ProfileImages;
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
        protected ProfileImages $profileImages,
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

    /**
     * Their name and email, and their profile image: an uploaded
     * $data['avatar'] replaces it, $data['remove_avatar'] takes it away, and
     * with neither it stays (ProfileImages).
     */
    public function update(User $user, array $data): void
    {
        $oldAvatar = $user->avatar;
        $data      = $this->profileImages->fill($data);

        $this->userRepository->update($user, $data);
        $this->profileImages->discardOld($oldAvatar, $data);

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

    /** Deletes their account, and their profile image with it. */
    public function delete(User $user): void
    {
        ActivityLogService::record('deleted', 'Deleted their own admin account', $user, null, $user);

        if ($this->userRepository->delete($user)) {
            $this->profileImages->delete($user->avatar);
        }
    }
}
