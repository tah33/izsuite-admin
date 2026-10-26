<?php

namespace App\Services\Admin;

use App\Models\User\User;
use App\Repositories\User\UserRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\Hash;

/**
 * Signing in to and out of the admin panel. Only admins get in.
 */
class AdminLoginService
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    public function credentialsMatchAnAdmin(string $email, string $password): bool
    {
        $user = $this->userRepository->findByGivenEmail($email);

        return $user && $user->isAdmin() && Hash::check($password, $user->password);
    }

    public function recordLogin(User $user): void
    {
        $this->userRepository->touchLastLogin($user);

        ActivityLogService::record('login', 'Logged into the web dashboard');
    }

    public function recordLogout(): void
    {
        ActivityLogService::record('logout', 'Logged out from the admin panel');
    }
}
