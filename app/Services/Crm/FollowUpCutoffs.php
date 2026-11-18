<?php

namespace App\Services\Crm;

use App\Models\User\CrmStage;
use App\Models\User\Workspace;
use App\Repositories\User\CrmPipelineRepository;
use App\Repositories\User\CrmSettingRepository;
use App\Services\Shared\BusinessCalendar;
use Carbon\CarbonInterface;

/**
 * The moments CRM's follow-up warnings are drawn at (Doc 17 section 17.23 -
 * Phase G3b), from Settings → CRM: shared by the Overview, which counts
 * them, and the Leads and Opportunities pages, which list them.
 *
 * - A stale opportunity entered its open stage longer ago than the stage's
 *   stale-after period (Phase D3a).
 * - A lead not contacted in time was added longer ago than the lead response
 *   period and never contacted.
 * - An opportunity closes soon when it is expected to close from the
 *   business's today to the end of the closing-soon period: as the Overview
 *   has counted it since Phase G1, and (Phase G4) the Dashboard counts it and
 *   the Opportunities page lists it.
 * - A quiet opportunity closes soon and has gone without activity for the
 *   recent-activity period: added before it, with no activity done and no
 *   e-mail sent since.
 */
class FollowUpCutoffs
{
    public function __construct(
        protected CrmPipelineRepository $pipelines,
        protected CrmSettingRepository $settings,
        protected BusinessCalendar $calendar,
    ) {}

    /**
     * Each open stage that goes stale, by its id: the moment an opportunity
     * at it must have entered it before.
     *
     * @return array<int, CarbonInterface>
     */
    public function staleSince(Workspace $workspace): array
    {
        return $this->pipelines->getFor($workspace)->flatMap->stages
            ->filter(fn (CrmStage $stage) => $stage->category === CrmStage::OPEN && $stage->stale_after_days !== null)
            ->mapWithKeys(fn (CrmStage $stage) => [$stage->id => now()->subDays($stage->stale_after_days)])
            ->all();
    }

    /** Before when a lead must have been added to be late for its first contact. */
    public function uncontactedBefore(Workspace $workspace): CarbonInterface
    {
        return now()->subDays($this->settings->forWorkspace($workspace)->lead_response_days);
    }

    /**
     * What makes an opportunity close soon: its expected closing day from
     * closing_from to closing_to, both business days.
     *
     * @return array{closing_from: string, closing_to: string}
     */
    public function closingSoon(Workspace $workspace): array
    {
        $today = $this->calendar->today($workspace);

        return [
            'closing_from' => $today->toDateString(),
            'closing_to'   => $today->addDays($this->settings->forWorkspace($workspace)->closing_soon_days)->toDateString(),
        ];
    }

    /**
     * What makes an opportunity quiet: closing soon (closing_from to
     * closing_to), and no activity since quiet_since.
     *
     * @return array{closing_from: string, closing_to: string, quiet_since: CarbonInterface}
     */
    public function quiet(Workspace $workspace): array
    {
        return [...$this->closingSoon($workspace), 'quiet_since' => now()->subDays($this->settings->forWorkspace($workspace)->recent_activity_days)];
    }
}
