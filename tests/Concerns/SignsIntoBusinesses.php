<?php

namespace Tests\Concerns;

use App\Http\Middleware\ResolveWorkspace;
use App\Models\User\User;
use App\Models\User\Workspace;
use Laravel\Sanctum\Sanctum;

/**
 * For tests of the APIs that answer for one business: signing in with a
 * business to answer for, and picking another one with the header.
 */
trait SignsIntoBusinesses
{
    /** The account's first business - the one the API answers for when none is picked. */
    protected function businessOf(User $user): Workspace
    {
        return $user->workspaces()->orderBy('id')->first()
            ?? $user->workspaces()->create(['name' => 'Main Business']);
    }

    protected function signIn(?User $user = null): User
    {
        $user ??= User::factory()->create();

        $this->businessOf($user);
        Sanctum::actingAs($user);

        return $user;
    }

    /** The header that picks $business for a request. */
    protected function pick(Workspace $business): array
    {
        return [ResolveWorkspace::HEADER => (string) $business->id];
    }
}
