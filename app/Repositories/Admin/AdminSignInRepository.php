<?php

namespace App\Repositories\Admin;

use App\Models\Admin\AdminSignIn;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;

/** The admin panel's sign-ins and failed attempts (Doc 15 section 15.2). */
class AdminSignInRepository
{
    public function create(array $data): AdminSignIn
    {
        return AdminSignIn::create($data);
    }

    /** @return Collection<int, AdminSignIn> $user's latest sign-ins and attempts, newest first */
    public function latestFor(User $user, int $limit = 10): Collection
    {
        return AdminSignIn::where('user_id', $user->id)->latest('id')->limit($limit)->get();
    }

    /** Whether $user has signed in to the admin panel before. */
    public function hasSignedIn(User $user): bool
    {
        return AdminSignIn::where('user_id', $user->id)->where('succeeded', true)->exists();
    }

    /** Whether $user has signed in before on the browser whose cookie hashes to $device. */
    public function knowsDevice(User $user, string $device): bool
    {
        return AdminSignIn::where('user_id', $user->id)->where('succeeded', true)->where('device', $device)->exists();
    }
}
