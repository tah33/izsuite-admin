<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Someone has just made their own izSuite account, from the register form.
 *
 * Fired once the account is saved - and only by self-registration: an account
 * made any other way (the admin panel, a seeder, a factory) does not fire it.
 * What a new account is given to start with hangs off it, one listener each,
 * all of them queued so registration never waits for them:
 *
 *  - CreateDefaultWorkspace - the "Billing Starter" workspace
 *  - CreateDefaultLocation  - the "Main Location"
 *  - CreateOwnerStaffMember - the owner, as the first of their own staff
 *
 * It carries the id rather than the User: a queued listener looks the account
 * up when it runs, and quietly does nothing if it has been deleted by then.
 *
 * An event of its own rather than Laravel's Illuminate\Auth\Events\Registered,
 * which the framework hangs its own e-mail verification on - this app verifies
 * an address with a code of its own.
 */
class UserRegistered
{
    use Dispatchable;

    public function __construct(public int $userId) {}
}
