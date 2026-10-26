<?php

namespace Database\Seeders;

use App\Jobs\AddOwnerAsStaff;
use App\Jobs\CreateMainLocation;
use App\Jobs\CreateStarterWorkspace;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Gives the seeded frontend account (user@izsuite.io) what registration gives
 * a new one - a "Billing Starter" workspace with its Lite apps on, a "Main
 * Location", and the owner as a staff member - so it can be signed in to and
 * used straight away.
 *
 * Runs after AppCatalogSeeder, so there are Lite apps to turn on. The jobs are
 * safe to run twice.
 *
 * DatabaseSeeder mutes model events, but registration's setup runs on them:
 * Workspace's creating hook gives the business its plan and base currency (a
 * NOT NULL column), and its created hook turns the plan's Lite apps on. So the
 * jobs run with events back on, and the mute is restored for the seeders after.
 */
class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'user@izsuite.io')->first();

        if (! $user) {
            return;
        }

        $dispatcher = Model::getEventDispatcher();
        Model::setEventDispatcher(app('events'));

        try {
            CreateStarterWorkspace::dispatchSync($user->id);
            CreateMainLocation::dispatchSync($user->id);
            AddOwnerAsStaff::dispatchSync($user->id);
        } finally {
            Model::setEventDispatcher($dispatcher);
        }
    }
}
