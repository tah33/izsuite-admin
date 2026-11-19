<?php

namespace App\Console\Commands;

use App\Services\Crm\LeadScoring;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Scores the open leads again (Doc 17 Phase I2) of each business with a lead
 * scoring rule switched on and CRM in use: how long a lead has gone without
 * activity, and whether it was contacted in time, change by themselves.
 * Scheduled every hour; only a score that changed is written.
 */
class ScoreCrmLeads extends Command
{
    protected $signature   = 'crm:score-leads';

    protected $description = 'Score the open CRM leads again by their scoring rules';

    public function handle(LeadScoring $scoring): int
    {
        $changed = $scoring->rescoreEveryBusiness();

        $this->info('Changed '.$changed.' lead '.Str::plural('score', $changed).'.');

        return self::SUCCESS;
    }
}
