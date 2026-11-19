<?php

namespace App\Jobs;

use App\Jobs\Concerns\SafeToRetry;
use App\Services\Crm\LeadScoring;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Scores a business's open leads again (Doc 17 Phase I2) once its scoring
 * rules change - added, changed or deleted - away from the request, as a
 * business may have many leads. Scoring again gives the same score, so it is
 * safe to try again.
 */
class ScoreLeads implements SafeToRetry, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds */
    public array $backoff = [30, 120];

    public function __construct(
        public int $workspaceId,
    ) {}

    public function handle(LeadScoring $scoring): void
    {
        $scoring->rescoreBusiness($this->workspaceId);
    }
}
