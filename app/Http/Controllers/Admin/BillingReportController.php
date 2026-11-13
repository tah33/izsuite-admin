<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BillingReportRequest;
use App\Services\Admin\BillingReportService;

/** Admin > Billing Reports (Doc 15 section 14 - Phase C2b): revenue, open and overdue invoices, failed payments and credits. */
class BillingReportController extends Controller
{
    public function __construct(
        protected BillingReportService $reports,
    ) {}

    public function index(BillingReportRequest $request)
    {
        try {
            return view('admin.billing-reports.index', $this->reports->report($request->validated()));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
