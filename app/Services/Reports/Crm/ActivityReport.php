<?php

namespace App\Services\Reports\Crm;

use App\Repositories\User\CrmActivityRepository;
use App\Repositories\User\ExpenseReportRepository;
use App\Repositories\User\PurchaseReportRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Crm\ActivityVisibility;
use App\Services\Reports\Report;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesFigures;
use App\Services\Shared\BusinessCalendar;

/**
 * CRM's activity reports (Doc 17 section 17.29 - Phase H3), each built from
 * the activities the user sees as on the Activities page
 * (ActivityVisibility), narrowed by whom they are given to - the owner
 * filter, as on the CRM Overview - and their location.
 */
abstract class ActivityReport extends Report
{
    /** How Activity Completion can group them, by month unless told. */
    public const GROUPS = ['month' => 'Month', 'assignee' => 'Assigned To', 'type' => 'Activity Type'];

    public function __construct(
        SalesReportRepository $sales,
        SalesFigures $figures,
        ReportPeriod $period,
        PurchaseReportRepository $purchases,
        ExpenseReportRepository $expenses,
        protected CrmActivityRepository $activities,
        protected ActivityVisibility $visibility,
        protected BusinessCalendar $calendar,
    ) {
        parent::__construct($sales, $figures, $period, $purchases, $expenses);
    }

    /** The report's filters as CrmActivityRepository narrows by them. */
    protected function narrowing(array $filters): array
    {
        return array_filter(['owner_id' => $filters['owner_id'] ?? null, 'location_id' => $filters['location_id'] ?? null]);
    }
}
