<?php

namespace Database\Seeders;

use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use Illuminate\Database\Seeder;

class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin   = User::where('role_id', 1)->first();
        $staff   = User::where('role_id', 2)->first();

        if (! $admin) {
            return;
        }

        $entries = [
            ['user_id' => $admin->id, 'action' => 'login',   'description' => 'Logged in to admin panel',                 'ip_address' => '127.0.0.1', 'created_at' => now()->subDays(6)],
            ['user_id' => $admin->id, 'action' => 'created', 'description' => 'Created staff member "John Doe"',          'model_type' => 'App\\Models\\User\\User', 'model_id' => $staff?->id, 'ip_address' => '127.0.0.1', 'created_at' => now()->subDays(5)],
            ['user_id' => $admin->id, 'action' => 'created', 'description' => 'Created role "Editor"',                    'model_type' => 'App\\Models\\Admin\\Role', 'model_id' => 2, 'ip_address' => '127.0.0.1', 'created_at' => now()->subDays(5)],
            ['user_id' => $admin->id, 'action' => 'updated', 'description' => 'Updated user status to "inactive"',        'model_type' => 'App\\Models\\User\\User', 'model_id' => 3, 'ip_address' => '127.0.0.1', 'created_at' => now()->subDays(4)],
            ['user_id' => $admin->id, 'action' => 'created', 'description' => 'Created ticket "Payment not processing"',  'model_type' => 'App\\Models\\User\\Ticket', 'model_id' => 1, 'ip_address' => '192.168.1.10', 'created_at' => now()->subDays(4)],
            ['user_id' => $admin->id, 'action' => 'replied', 'description' => 'Replied to ticket #1',                    'model_type' => 'App\\Models\\User\\Ticket', 'model_id' => 1, 'ip_address' => '192.168.1.10', 'created_at' => now()->subDays(3)],
            ['user_id' => $admin->id, 'action' => 'updated', 'description' => 'Updated ticket #1 status to "resolved"',   'model_type' => 'App\\Models\\User\\Ticket', 'model_id' => 1, 'ip_address' => '127.0.0.1', 'created_at' => now()->subDays(3)],
            ['user_id' => $staff?->id ?? $admin->id, 'action' => 'login',   'description' => 'Logged in to admin panel',  'ip_address' => '10.0.0.5', 'created_at' => now()->subDays(2)],
            ['user_id' => $admin->id, 'action' => 'updated', 'description' => 'Updated role "Admin" permissions',         'model_type' => 'App\\Models\\Admin\\Role', 'model_id' => 2, 'ip_address' => '127.0.0.1', 'created_at' => now()->subDays(2)],
            ['user_id' => $admin->id, 'action' => 'created', 'description' => 'Created page "Privacy Policy"',            'model_type' => 'App\\Models\\Frontend\\Page', 'model_id' => 1, 'ip_address' => '127.0.0.1', 'created_at' => now()->subDays(1)],
            ['user_id' => $admin->id, 'action' => 'updated', 'description' => 'Updated page "Terms of Service"',          'model_type' => 'App\\Models\\Frontend\\Page', 'model_id' => 2, 'ip_address' => '127.0.0.1', 'created_at' => now()->subDays(1)],
            ['user_id' => $admin->id, 'action' => 'deleted', 'description' => 'Deleted staff member "Jane Test"',         'model_type' => 'App\\Models\\User\\User', 'model_id' => 99, 'ip_address' => '127.0.0.1', 'created_at' => now()->subHours(12)],
            ['user_id' => $admin->id, 'action' => 'login',   'description' => 'Logged in to admin panel',                 'ip_address' => '127.0.0.1', 'created_at' => now()->subHours(6)],
            ['user_id' => $admin->id, 'action' => 'created', 'description' => 'Created ticket "Billing inquiry"',         'model_type' => 'App\\Models\\User\\Ticket', 'model_id' => 2, 'ip_address' => '127.0.0.1', 'created_at' => now()->subHours(3)],
            ['user_id' => $admin->id, 'action' => 'replied', 'description' => 'Replied to ticket #2',                    'model_type' => 'App\\Models\\User\\Ticket', 'model_id' => 2, 'ip_address' => '127.0.0.1', 'created_at' => now()->subHours(2)],
        ];

        foreach ($entries as $entry) {
            ActivityLog::create($entry);
        }
    }
}
