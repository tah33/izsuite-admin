<?php

namespace Database\Seeders;

use App\Models\Admin\Role;
use App\Models\User\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $recruiterRoleId = Role::where('slug', 'recruiter')->value('id');
        $candidateRoleId = Role::where('slug', 'candidate')->value('id');
        $userRoleId      = Role::where('slug', 'user')->value('id');

        User::firstOrCreate(
            ['email' => 'admin@izsuite.io'],
            [
                'name'     => 'Admin',
                'password' => '123456',
                'role_id'  => 1,
            ]
        );

        User::firstOrCreate(
            ['email' => 'recruiter@izsuite.io'],
            [
                'name'     => 'Recruiter Demo',
                'password' => '123456',
                'role_id'  => $recruiterRoleId,
            ]
        );

        User::firstOrCreate(
            ['email' => 'candidate@izsuite.io'],
            [
                'name'     => 'Candidate Demo',
                'password' => '123456',
                'role_id'  => $candidateRoleId,
            ]
        );

        User::firstOrCreate(
            ['email' => 'user@izsuite.io'],
            [
                'name'              => 'izSuite User',
                'password'          => '123456',
                'role_id'           => $userRoleId,
                'status'            => 'active',
                'email_verified_at' => now(),
            ]
        );

        // Left unverified on purpose - the counterpart to the account above, so
        // the "verify your email first" rejection can be exercised without
        // editing rows by hand.
        User::firstOrCreate(
            ['email' => 'unverified@izsuite.io'],
            [
                'name'              => 'Unverified User',
                'password'          => '123456',
                'role_id'           => $userRoleId,
                'status'            => 'active',
                'email_verified_at' => null,
            ]
        );
    }
}
