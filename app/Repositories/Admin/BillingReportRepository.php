<?php

namespace App\Repositories\Admin;

use App\Models\Billing\SubscriptionCredit;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\SubscriptionRefund;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin > Billing Reports (Doc 15 section 14 - Phase C2b): izSuite's own
 * billing summed - invoices paid and refunds by month, plan and currency,
 * invoices open and overdue, failed card payments, and credits - never a
 * business's own transactions. Months are of the times as stored (UTC).
 */
class BillingReportRepository
{
    /** Invoices paid from $from to $to, by month and currency: how many, what they came to, and their tax. */
    public function paidByMonth(CarbonInterface $from, CarbonInterface $to, ?string $currency, ?string $plan): Collection
    {
        $month = $this->month('paid_at');

        return $this->paid($from, $to, $currency, $plan)
            ->selectRaw("{$month} as month, currency, count(*) as invoices, sum(total) as collected, sum(tax) as tax")
            ->groupByRaw("{$month}, currency")
            ->get();
    }

    /** As paidByMonth(), by plan. */
    public function paidByPlan(CarbonInterface $from, CarbonInterface $to, ?string $currency, ?string $plan): Collection
    {
        return $this->paid($from, $to, $currency, $plan)
            ->selectRaw('package_name as plan, currency, count(*) as invoices, sum(total) as collected, sum(tax) as tax')
            ->groupBy('package_name', 'currency')
            ->get();
    }

    /** Refunds confirmed from $from to $to, by month and currency - of $plan's invoices only, when given. */
    public function refundsByMonth(CarbonInterface $from, CarbonInterface $to, ?string $currency, ?string $plan): Collection
    {
        $month = $this->month('subscription_refunds.confirmed_at');

        return $this->refunds($from, $to, $currency, $plan)
            ->selectRaw("{$month} as month, subscription_refunds.currency as currency, sum(subscription_refunds.amount) as refunded")
            ->groupByRaw("{$month}, subscription_refunds.currency")
            ->get();
    }

    /** As refundsByMonth(), by the plan of the invoice each gives back - one without an invoice is left out. */
    public function refundsByPlan(CarbonInterface $from, CarbonInterface $to, ?string $currency, ?string $plan): Collection
    {
        return $this->refunds($from, $to, $currency, $plan)
            ->join('subscription_invoices', 'subscription_invoices.id', '=', 'subscription_refunds.subscription_invoice_id')
            ->selectRaw('subscription_invoices.package_name as plan, subscription_refunds.currency as currency, sum(subscription_refunds.amount) as refunded')
            ->groupBy('subscription_invoices.package_name', 'subscription_refunds.currency')
            ->get();
    }

    /** Invoices waiting for payment, by currency: all of them, and those past their due day on $today. */
    public function open(string $today, ?string $currency): Collection
    {
        return SubscriptionInvoice::query()
            ->where('status', SubscriptionInvoice::OPEN)
            ->when($currency, fn (Builder $query) => $query->where('currency', $currency))
            ->selectRaw('currency, count(*) as invoices, sum(total) as owed, sum(case when date(due_on) < ? then 1 else 0 end) as overdue, sum(case when date(due_on) < ? then total else 0 end) as overdue_owed', [$today, $today])
            ->groupBy('currency')
            ->get();
    }

    /** The invoices longest overdue on $today, with their business. */
    public function overdue(string $today, ?string $currency, int $limit = 20): Collection
    {
        return SubscriptionInvoice::with('workspace:id,name')
            ->where('status', SubscriptionInvoice::OPEN)
            ->whereDate('due_on', '<', $today)
            ->when($currency, fn (Builder $query) => $query->where('currency', $currency))
            ->orderBy('due_on')
            ->limit($limit)
            ->get(['id', 'workspace_id', 'number', 'package_name', 'currency', 'total', 'due_on']);
    }

    /** Card tries that did not go through, from $from to $to, by month and currency: how many, for how many businesses, and how much. */
    public function failedByMonth(CarbonInterface $from, CarbonInterface $to, ?string $currency): Collection
    {
        $month = $this->month('created_at');

        return $this->failed($from, $to, $currency)
            ->selectRaw("{$month} as month, currency, count(*) as tries, count(distinct workspace_id) as businesses, sum(amount) as amount")
            ->groupByRaw("{$month}, currency")
            ->get();
    }

    /** The latest card tries that did not go through, with their business and invoice. */
    public function latestFailures(CarbonInterface $from, CarbonInterface $to, ?string $currency, int $limit = 20): Collection
    {
        return $this->failed($from, $to, $currency)
            ->with(['workspace:id,name', 'invoice:id,number'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /** izSuite's credit by currency: given and taken off invoices from $from to $to, and what is left now. */
    public function credits(CarbonInterface $from, CarbonInterface $to, ?string $currency): Collection
    {
        [$start, $end] = [$from->copy()->startOfDay()->toDateTimeString(), $to->copy()->endOfDay()->toDateTimeString()];

        return SubscriptionCredit::query()
            ->when($currency, fn (Builder $query) => $query->where('currency', $currency))
            ->selectRaw(
                'currency, sum(case when kind = ? and created_at between ? and ? then amount else 0 end) as granted, '
                .'sum(case when kind = ? and created_at between ? and ? then -amount else 0 end) as used, sum(amount) as outstanding',
                [SubscriptionCredit::GRANTED, $start, $end, SubscriptionCredit::USED, $start, $end],
            )
            ->groupBy('currency')
            ->get();
    }

    /** @return list<string> the currencies izSuite has invoiced in */
    public function currencies(): array
    {
        return SubscriptionInvoice::query()->distinct()->orderBy('currency')->pluck('currency')->all();
    }

    /** @return list<string> the plans izSuite has invoiced, by the name on their invoices */
    public function plans(): array
    {
        return SubscriptionInvoice::query()->distinct()->orderBy('package_name')->pluck('package_name')->all();
    }

    private function paid(CarbonInterface $from, CarbonInterface $to, ?string $currency, ?string $plan): Builder
    {
        return SubscriptionInvoice::query()
            ->where('status', SubscriptionInvoice::PAID)
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($currency, fn (Builder $query) => $query->where('currency', $currency))
            ->when($plan, fn (Builder $query) => $query->where('package_name', $plan));
    }

    private function refunds(CarbonInterface $from, CarbonInterface $to, ?string $currency, ?string $plan): Builder
    {
        return SubscriptionRefund::query()
            ->where('subscription_refunds.status', SubscriptionRefund::SUCCEEDED)
            ->whereBetween('subscription_refunds.confirmed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($currency, fn (Builder $query) => $query->where('subscription_refunds.currency', $currency))
            ->when($plan, fn (Builder $query) => $query->whereHas('invoice', fn (Builder $invoice) => $invoice->where('package_name', $plan)));
    }

    private function failed(CarbonInterface $from, CarbonInterface $to, ?string $currency): Builder
    {
        return SubscriptionPayment::query()
            ->whereIn('status', [SubscriptionPayment::FAILED, SubscriptionPayment::ACTION_REQUIRED])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($currency, fn (Builder $query) => $query->where('currency', $currency));
    }

    /** "2026-11" of $column, in this database's own words. */
    private function month(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', {$column})" : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
