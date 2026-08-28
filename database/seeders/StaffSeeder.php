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
                'name'     => 'Staff Demo',
                'password' => 'password',
                'role_id'  => $staffRole->id,
                'status'   => 'active',
            ]
        );

        User::firstOrCreate(
            ['email' => 'editor@resumist.test'],
            [
                'name'     => 'Editor Demo',
                'password' => 'password',
                'role_id'  => $staffRole->id,
                'status'   => 'active',
            ]
        );
    }
}
