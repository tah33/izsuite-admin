<?php

namespace Database\Seeders;

use App\Models\Admin\Role;
use App\Models\User\User;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $staffRole = Role::where('slug', 'staff')->first();

        if (! $staffRole) {
            return;
        }

        User::firstOrCreate(
            ['email' => 'staff@resumist.test'],
            [
                'first_name' => 'Staff',
                'last_name'  => 'Demo',
                'password'   => 'password',
                'role_id'    => $staffRole->id,
                'status'     => 'active',
            ]
        );

        User::firstOrCreate(
            ['email' => 'editor@resumist.test'],
            [
                'first_name' => 'Editor',
                'last_name'  => 'Demo',
                'password'   => 'password',
                'role_id'    => $staffRole->id,
                'status'     => 'active',
            ]
        );
    }
}
