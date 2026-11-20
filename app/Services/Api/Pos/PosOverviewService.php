<?php

namespace App\Services\Api\Pos;

use App\Models\User\Workspace;
use App\Repositories\User\PosOverviewRepository;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\WorkspaceAccess;
use App\Support\Decimal;

/**
 * POS today (Doc 18 sections 18.3 and 18.35 - Phase G2), for its Overview
 * and the Dashboard's POS card: the business's day, at the locations the user
 * may use - or one of them - in the base currency. Its sales - how many,
 * their total and the average - what each payment method took, its returns
 * and what they paid back, the sessions closed with a variance, the items
 * that took the most, and the sessions open now.
 */
class PosOverviewService
{
    public function __construct(
        protected PosOverviewRepository $overview,
        protected BusinessCalendar $calendar,
        protected WorkspaceAccess $access,
    ) {}

    public function today(Workspace $workspace, ?int $locationId = null): array
    {
        $day       = $this->calendar->today($workspace);
        $from      = $day->utc();
        $to        = $day->endOfDay()->utc();
        $locations = $this->locations($locationId);
        $sales     = $this->overview->sales($workspace, $from, $to, $locations);

        return [
            'date'          => $day->toDateString(),
            'currency'      => $workspace->base_currency,
            'sales'         => [...$sales, 'average' => $sales['count'] > 0 ? Decimal::round(bcdiv($sales['total'], (string) $sales['count'], 4)) : '0.00'],
            'payments'      => $this->overview->payments($workspace, $from, $to, $locations)->all(),
            'returns'       => $this->overview->returns($workspace, $from, $to, $locations),
            'variances'     => $this->overview->variances($workspace, $from, $to, $locations)->all(),
            'top_items'     => $this->overview->topItems($workspace, $from, $to, $locations)->all(),
            'open_sessions' => $this->overview->openSessions($workspace, $locations)->all(),
        ];
    }

    /** The user's locations - every one, for null - or $locationId of them alone. */
    private function locations(?int $locationId): ?array
    {
        $allowed = $this->access->locations();

        if ($locationId === null) {
            return $allowed;
        }

        return $allowed === null || in_array($locationId, $allowed, true) ? [$locationId] : [];
    }
}
