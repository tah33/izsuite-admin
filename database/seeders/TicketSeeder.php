<?php

namespace Database\Seeders;

use App\Models\User\Ticket;
use App\Models\User\TicketMessage;
use App\Models\User\User;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $users   = User::whereHas('role', fn ($query) => $query->whereIn('slug', ['recruiter', 'candidate']))->take(5)->get();
        $adminId = User::whereHas('role', fn ($query) => $query->whereIn('slug', ['admin', 'staff', 'super-admin']))->value('id');

        if ($users->isEmpty()) {
            return;
        }

        $ticket1 = Ticket::create([
            'user_id'    => $users->first()->id,
            'subject'    => 'Cannot access my dashboard',
            'status'     => 'open',
            'priority'   => 'high',
            'created_at' => now()->subDays(2),
        ]);
        TicketMessage::create([
            'ticket_id'  => $ticket1->id,
            'user_id'    => $users->first()->id,
            'message'    => 'I keep getting redirected after login and cannot reach my account.',
            'created_at' => now()->subDays(2),
        ]);

        $ticket2 = Ticket::create([
            'user_id'    => $users->get(1)?->id ?? $users->first()->id,
            'subject'    => 'Feature request: more profile fields',
            'status'     => 'in_progress',
            'priority'   => 'low',
            'created_at' => now()->subDays(5),
        ]);
        TicketMessage::create([
            'ticket_id'  => $ticket2->id,
            'user_id'    => $ticket2->user_id,
            'message'    => 'Would love more profile fields for resumes and job preferences.',
            'created_at' => now()->subDays(5),
        ]);
        TicketMessage::create([
            'ticket_id'  => $ticket2->id,
            'user_id'    => $adminId,
            'message'    => 'Thanks, our team is reviewing this request.',
            'created_at' => now()->subDays(4),
        ]);

        $ticket3 = Ticket::create([
            'user_id'    => $users->get(2)?->id ?? $users->first()->id,
            'subject'    => 'How do I change my email?',
            'status'     => 'resolved',
            'priority'   => 'medium',
            'created_at' => now()->subWeek(),
        ]);
        TicketMessage::create([
            'ticket_id'  => $ticket3->id,
            'user_id'    => $ticket3->user_id,
            'message'    => 'Is there a way to update my email address from the account area?',
            'created_at' => now()->subWeek(),
        ]);
        TicketMessage::create([
            'ticket_id'  => $ticket3->id,
            'user_id'    => $adminId,
            'message'    => 'Yes, please update it from your profile settings page.',
            'created_at' => now()->subWeek()->addHours(2),
        ]);
        TicketMessage::create([
            'ticket_id'  => $ticket3->id,
            'user_id'    => $ticket3->user_id,
            'message'    => 'Found it, thanks!',
            'created_at' => now()->subWeek()->addHours(3),
        ]);
    }
}
