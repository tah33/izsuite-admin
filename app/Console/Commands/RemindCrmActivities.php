<?php

namespace App\Console\Commands;

use App\Services\Crm\ActivityNotices;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Sends CRM activities' reminders whose moment has come (Doc 17 Phase E2):
 * to each planned activity's assignee, once, in the bell and by e-mail as
 * they have chosen. Scheduled every minute.
 */
class RemindCrmActivities extends Command
{
    protected $signature   = 'crm:remind-activities';

    protected $description = "Send CRM activities' reminders whose moment has come";

    public function handle(ActivityNotices $notices): int
    {
        $sent = $notices->remindDue();

        $this->info('Sent '.$sent.' '.Str::plural('reminder', $sent).'.');

        return self::SUCCESS;
    }
}
