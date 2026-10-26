<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A frontend account has just been saved by registration.
 *
 * Carries the id only: its listener queues jobs, and those look the account up
 * when they run - by then it may have changed, or be gone.
 */
class UserRegistered
{
    use Dispatchable;

    public function __construct(
        public int $userId,
    ) {}
}
