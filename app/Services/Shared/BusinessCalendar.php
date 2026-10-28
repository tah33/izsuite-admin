<?php

namespace App\Services\Shared;

use App\Models\User\Workspace;
use App\Repositories\User\BusinessSettingRepository;
use App\Support\Timezone;
use Carbon\CarbonImmutable;

/**
 * A business's own calendar (Doc 14 section 14.2): what "today" is in its time
 * zone - the one in Business Settings, or the signed-in owner's own while the
 * business has never saved its settings (Doc 02's rule) - and the month its
 * financial year starts in.
 */
class BusinessCalendar
{
    public function __construct(
        private BusinessSettingRepository $businessSettingRepository,
    ) {}

    public function timezone(Workspace $workspace): string
    {
        return Timezone::resolve($this->businessSettingRepository->findFor($workspace)?->timezone, app_display_timezone());
    }

    public function today(Workspace $workspace): CarbonImmutable
    {
        return now($this->timezone($workspace))->toImmutable()->startOfDay();
    }

    /** 1 (January) to 12. January until the business says otherwise. */
    public function financialYearStart(Workspace $workspace): int
    {
        return $this->businessSettingRepository->findFor($workspace)?->financial_year_start_month ?: 1;
    }
}
