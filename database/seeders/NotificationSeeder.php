<?php

namespace Database\Seeders;

use App\Models\Admin\Role;
use App\Models\User\User;
use App\Notifications\SystemAlertNotification;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = User::where('role_id', Role::SUPER_ADMIN_ID)->first();

        if ($superAdmin) {
            $superAdmin->notify(new SystemAlertNotification(
                'New Subscription',
                'A user just purchased the Pro Yearly plan.',
                route('admin.users.index'),
                'success'
            ));

            $superAdmin->notify(new SystemAlertNotification(
                'System Warning',
                'Your SMTP configuration seems to be failing.',
                route('admin.smtp.index'),
                'warning'
            ));

            $superAdmin->notify(new SystemAlertNotification(
                'Ticket Update',
                'User #120 updated their support ticket.',
                route('admin.tickets.index'),
                'info'
            ));
        }
    }
}
