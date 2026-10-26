<?php

namespace Database\Seeders;

use App\Jobs\AddOwnerAsStaff;
use App\Jobs\CreateMainLocation;
use App\Jobs\CreateStarterWorkspace;
use App\Models\User\User;
use Illuminate\Database\Seeder;

/**
 * Gives the seeded frontend account (user@izsuite.io) what registration gives
 * a new one - a "Billing Starter" workspace with its Lite apps on, a "Main
 * Location", and the owner as a staff member - so it can be signed in to and
 * used straight away.
 *
 * Runs after AppCatalogSeeder, so there are Lite apps to turn on. The jobs are
 * safe to run twice.
 */
class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'user@izsuite.io')->first();

        if (! $user) {
            return;
        }

        CreateStarterWorkspace::dispatchSync($user->id);
        CreateMainLocation::dispatchSync($user->id);
        AddOwnerAsStaff::dispatchSync($user->id);
    }
}
