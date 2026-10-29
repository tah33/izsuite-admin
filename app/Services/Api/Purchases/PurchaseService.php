<?php

namespace App\Services\Api\Purchases;

use App\Enums\PurchaseStatus;
use App\Models\User\Purchase;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\PurchaseRepository;
use App\Repositories\User\PurchaseSettingRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Api\Sales\SalesLines;
use App\Services\Purchases\PurchaseRules;
use App\Services\Sales\SalesCalculator;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Support\Csv;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Purchases Lite's purchases (Doc 09): the list, one purchase, a draft added,
 * changed or deleted, and the list as CSV. Recording and voiding are
 * PurchaseFlow's.
 *
 * Every save works the totals out again from the lines with SalesCalculator -
 * the same arithmetic as a sale: what a form shows is never taken as a total.
 * A purchase takes the business's tax settings when it is made and keeps them.
 * A recorded purchase cannot be changed (section 10).
 */
class PurchaseService
{
    use RefusesConflicts;

    private const TOTALS = ['subtotal', 'discount_total', 'tax_total', 'grand_total', 'net_total', 'base_grand_total', 'base_tax_total', 'base_net_total', 'taxes'];

    public function __construct(
        protected PurchaseRepository $purchaseRepository,
        protected PurchaseSettingRepository $purchaseSettingRepository,
        protected ContactRepository $contactRepository,
        protected PurchaseLines $purchaseLines,
        protected SalesLines $salesLines,
        protected SalesCalculator $salesCalculator,
        protected PurchaseRules $purchaseRules,
        protected BusinessCalendar $businessCalendar,
    ) {}

    /** The business's today, which overdue is measured by: "2026-10-28". */
    public function today(Workspace $workspace): string
    {
        return $this->businessCalendar->today($workspace)->toDateString();
    }

    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->purchaseRepository->paginateFor($workspace, $filters, $perPage, $this->today($workspace));
    }

    /** Another business's purchase answers like one that does not exist. */
    public function find(Workspace $workspace, int $id): Purchase
    {
        return $this->purchaseRepository->findFor($workspace, $id) ?? abort(404, 'Purchase not found.');
    }

    /** $data is validated (see SavePurchaseRequest). */
    public function create(Workspace $workspace, User $user, array $data): Purchase
    {
        $purchase = DB::transaction(function () use ($workspace, $user, $data) {
            $header = [
                ...$this->header($workspace, $data),
                'price_tax_mode' => $workspace->price_tax_mode,
                'tax_rounding'   => $workspace->tax_rounding,
                'created_by'     => $user->id,
                'updated_by'     => $user->id,
            ];

            [$totals, $rows] = $this->totals($header, $this->purchaseLines->rows($workspace, $data['lines']));

            $purchase = $this->purchaseRepository->create($workspace, [...$header, ...$totals]);
            $this->purchaseRepository->replaceLines($purchase, $rows);

            return $purchase;
        });

        $purchase = $this->purchaseRepository->loadShown($purchase);

        // Each write is logged here, by what it is, so LogApiWriteActivity
        // does not add its own vaguer entry.
        ActivityLogService::record('created', 'Added '.$this->describe($purchase), $purchase, ['source' => 'api']);

        return $purchase;
    }

    public function update(Workspace $workspace, int $id, User $user, array $data): Purchase
    {
        $purchase = $this->find($workspace, $id);

        if (! $this->purchaseRules->allows($purchase, 'edit')) {
            $this->refuse('A recorded purchase cannot be changed. Void it and record it again if it is wrong.');
        }

        DB::transaction(function () use ($workspace, $user, $data, $purchase) {
            $header = [...$this->header($workspace, $data), 'updated_by' => $user->id];

            [$totals, $rows] = $this->totals([...$header, ...$purchase->only(['price_tax_mode', 'tax_rounding'])], $this->purchaseLines->rows($workspace, $data['lines']));

            $this->purchaseRepository->update($purchase, [...$header, ...$totals]);
            $this->purchaseRepository->replaceLines($purchase, $rows);
        });

        ActivityLogService::record('updated', 'Updated '.$this->describe($purchase), $purchase, ['source' => 'api']);

        return $this->purchaseRepository->loadShown($purchase);
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $purchase = $this->find($workspace, $id);

        if (! $this->purchaseRules->allows($purchase, 'delete')) {
            $this->refuse('Only a draft can be deleted. Void a recorded purchase instead.');
        }

        $this->purchaseRepository->delete($purchase);

        ActivityLogService::record('deleted', 'Deleted '.$this->describe($purchase), $purchase, ['source' => 'api']);
    }

    /**
     * The totals of a purchase with $header's discount, tax settings and
     * exchange rate, and its line rows with their amounts filled in.
     *
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    public function totals(array $header, array $rows): array
    {
        $result = $this->salesCalculator->calculate(
            array_map(fn (array $row) => $this->salesLines->forCalculation($row), $rows),
            $header['discount_type'] ?? null,
            isset($header['discount_value']) ? (string) $header['discount_value'] : null,
            $header['price_tax_mode'],
            $header['tax_rounding'],
            (string) $header['exchange_rate'],
        );

        foreach ($rows as $key => $row) {
            $rows[$key] = [...$row, ...$result['lines'][$key]];
        }

        return [Arr::only($result, self::TOTALS), $rows];
    }

    /** The list as the page shows it, whole, as CSV (section 10: Export) - logged. */
    public function export(Workspace $workspace, array $filters): StreamedResponse
    {
        $today = $this->today($workspace);
        $rows  = $this->purchaseRepository->lazyFor($workspace, $filters, $today)->map(fn (Purchase $purchase) => [
            $purchase->document_date,
            $purchase->number ?? 'Draft',
            $purchase->supplier_invoice_number,
            $purchase->supplier_snapshot['name'] ?? $purchase->contact?->name,
            $purchase->status->label(),
            $purchase->status === PurchaseStatus::Recorded ? Str::headline($purchase->payment_status) : '',
            $this->purchaseRules->overdue($purchase, $today) ? 'Overdue' : '',
            $purchase->due_date,
            $purchase->currency,
            $purchase->grand_total,
            $purchase->amount_paid,
            $purchase->balance_due,
            $purchase->location?->name,
        ]);

        ActivityLogService::record('exported', 'Exported purchases', $workspace, ['source' => 'api']);

        return Csv::download("purchases-{$today}.csv", [
            'Date', 'Number', 'Supplier Invoice Number', 'Supplier', 'Status', 'Payment Status', 'Overdue', 'Due Date', 'Currency', 'Total', 'Amount Paid', 'Balance Due', 'Location',
        ], $rows);
    }

    /** What the log calls it: "purchase PUR-00001", or "draft purchase from "Karim Stores"". */
    public function describe(Purchase $purchase): string
    {
        return $purchase->number
            ? "purchase {$purchase->number}"
            : 'draft purchase from "'.Str::limit($purchase->contact?->name ?? 'a supplier', 100).'"';
    }

    /**
     * The header fields as kept. The due date is the form's, or its date plus
     * the payment terms - the form's, the supplier's or the business's (Doc 09
     * section 5). A purchase in the base currency has an exchange rate of 1.
     */
    private function header(Workspace $workspace, array $data): array
    {
        $discount = ($data['discount_type'] ?? null) && ! Decimal::isZero(isset($data['discount_value']) ? (string) $data['discount_value'] : null);
        $terms    = $this->paymentTerms($workspace, $data);

        return [
            'contact_id'              => $data['contact_id'],
            'location_id'             => $data['location_id'] ?? null,
            'document_date'           => $data['document_date'],
            'due_date'                => $data['due_date'] ?? CarbonImmutable::parse($data['document_date'])->addDays($terms)->toDateString(),
            'payment_terms'           => $terms,
            'currency'                => $data['currency'],
            'exchange_rate'           => $data['currency'] === $workspace->base_currency ? '1' : (string) $data['exchange_rate'],
            'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
            'supplier_invoice_date'   => $data['supplier_invoice_date'] ?? null,
            'reference'               => $data['reference'] ?? null,
            'discount_type'           => $discount ? $data['discount_type'] : null,
            'discount_value'          => $discount ? (string) $data['discount_value'] : null,
            'notes'                   => $data['notes'] ?? null,
        ];
    }

    private function paymentTerms(Workspace $workspace, array $data): int
    {
        return (int) ($data['payment_terms']
            ?? $this->contactRepository->findFor($workspace, $data['contact_id'])?->supplier_payment_terms
            ?? $this->purchaseSettingRepository->forWorkspace($workspace)->payment_terms);
    }
}
