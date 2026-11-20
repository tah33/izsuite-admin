<?php

namespace App\Repositories\User;

use App\Models\User\User;

/**
 * Where a cashier's POS PIN is kept (Doc 18 section 18.31 - Phase I1): on
 * their account, hashed - one PIN for the person, wherever they sell, owner
 * or member.
 */
class PosPinRepository
{
    /** $user's PIN, hashed - none until they set one. */
    public function pinOf(User $user): ?string
    {
        return User::query()->whereKey($user->id)->value('pos_pin');
    }

    /** Keeps $hash as their PIN, from now. */
    public function save(User $user, string $hash): void
    {
        $user->forceFill(['pos_pin' => $hash, 'pos_pin_set_at' => now()])->save();
    }
}
