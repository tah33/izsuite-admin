<?php

namespace Database\Seeders;

use App\Models\Admin\Role;
use App\Models\User\User;
use Illuminate\Database\Seeder;

class CandidateSeeder extends Seeder
{
    public function run(): void
    {
        $candidateRoleId = Role::where('slug', 'candidate')->value('id');

        User::factory(15)->create(['role_id' => $candidateRoleId]);
    }
}
