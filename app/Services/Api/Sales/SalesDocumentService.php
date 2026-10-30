<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\SalesSettingRepository;
use App\Services\Sales\SalesCalculator;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sales Lite's quotations, proforma invoices and invoices as they are made
 * and changed (Doc 08 sections 4 to 12): the lists, one document, a new one,
 * an edit, and deleting a draft. Issuing, sending and the other status changes
 * are SalesDocumentFlow's; conversions SalesConversionService's.
 *
 * Every save works the totals out again from the lines (SalesCalculator):
 * what a form shows is never taken as a total. A document takes the business's
 * tax settings when it is made and keeps them, so its totals do not move when
 * those change.
 *
 * An issued invoice cannot be changed. A sent quotation or proforma can, and
 * when the change is one its customer would see, the version before it is
 * kept as a revision (Doc 08 section 12).
 */
class SalesDocumentService
{
    use RefusesConflicts;

    private const TOTALS = ['subtotal', 'discount_total', 'tax_total', 'grand_total', 'net_total', 'base_grand_total', 'base_tax_total', 'base_net_total', 'taxes'];

    public function __construct(
        protected SalesDocumentRepository $salesDocumentRepository,
        protected SalesSettingRepository $salesSettingRepository,
        protected ContactRepository $contactRepository,
        protected SalesLines $salesLines,
        protected SalesSnapshots $salesSnapshots,
        protected SalesCalculator $salesCalculator,
        protected SalesDocumentRules $salesDocumentRules,
        protected BusinessCalendar $businessCalendar,
    ) {}

    /** The business's today, which expiry and overdue are measured by: "2026-10-28". */
    public function today(Workspace $workspace): string
    {
        return $this->businessCalendar->today($workspace)->toDateString();
    }

    public function list(Workspace $workspace, DocumentType $type, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->salesDocumentRepository->paginateFor($workspace, $type, $filters, $perPage, $this->today($workspace));
    }

    /** Another business's document - or one of another type - answers like one that does not exist. */
    public function find(Workspace $workspace, DocumentType $type, int $id): SalesDocument
    {
        return $this->salesDocumentRepository->findFor($workspace, $type, $id)
            ?? abort(404, "{$type->label()} not found.");
    }

    /** $data is validated (see SaveSalesDocumentRequest). */
    public function create(Workspace $workspace, DocumentType $type, User $user, array $data): SalesDocument
    {
        $document = DB::transaction(function () use ($workspace, $type, $user, $data) {
            $header = [
                ...$this->header($workspace, $type, $data),
                'type'           => $type,
                'price_tax_mode' => $workspace->price_tax_mode,
                'tax_rounding'   => $workspace->tax_rounding,
                'created_by'     => $user->id,
                'updated_by'     => $user->id,
            ];

            [$totals, $rows] = $this->totals($header, $this->salesLines->rows($workspace, $data['lines'] ?? []));

            $document = $this->salesDocumentRepository->create($workspace, [...$header, ...$totals]);
            $this->salesDocumentRepository->replaceLines($document, $rows);

            return $document;
        });

        $document = $this->salesDocumentRepository->loadShown($document);

        // Each write is logged here, by what it is, so LogApiWriteActivity does
        // not add its own vaguer "POST api/v1/sales/invoices" entry.
        ActivityLogService::record('created', 'Added '.$this->describe($document), $document, ['source' => 'api']);

        return $document;
    }

    public function update(Workspace $workspace, DocumentType $type, int $id, User $user, array $data): SalesDocument
    {
        $document = $this->find($workspace, $type, $id);
        $today    = $this->today($workspace);

        if (! $this->salesDocumentRules->allows($document, 'edit', $today)) {
            $this->refuse($type === DocumentType::Invoice
                ? 'An issued invoice cannot be changed. Correct it with a credit note.'
                : "This {$this->noun($type)} is {$this->salesDocumentRules->status($document, $today)} and can no longer be changed.");
        }

        $before  = $this->salesSnapshots->document($document);
        $revised = DB::transaction(function () use ($workspace, $type, $user, $data, $document, $before) {
            $header = [...$this->header($workspace, $type, $data), 'updated_by' => $user->id];

            [$totals, $rows] = $this->totals([...$header, ...$document->only(['price_tax_mode', 'tax_rounding'])], $this->salesLines->rows($workspace, $data['lines'] ?? []));

            $this->salesDocumentRepository->update($document, [...$header, ...$totals]);
            $this->salesDocumentRepository->replaceLines($document, $rows);

            $after = $this->salesSnapshots->document($this->salesDocumentRepository->loadShown($document));

            if ($document->status !== SalesDocumentStatus::Sent || ! $this->salesSnapshots->differ($before, $after)) {
                return false;
            }

            $this->salesDocumentRepository->addRevision($document, $document->revision, $before, $user->id);
            $this->salesDocumentRepository->update($document, ['revision' => $document->revision + 1]);

            return true;
        });

        ActivityLogService::record('updated', 'Updated '.$this->describe($document).($revised ? " - now revision {$document->revision}" : ''), $document, ['source' => 'api']);

        return $this->salesDocumentRepository->loadShown($document);
    }

    public function delete(Workspace $workspace, DocumentType $type, int $id): void
    {
        $document = $this->find($workspace, $type, $id);

        if (! $this->salesDocumentRules->allows($document, 'delete', $this->today($workspace))) {
            $this->refuse('Only a draft can be deleted. '.($type->isOffer() ? 'Cancel it instead.' : "Void an issued {$this->noun($type)} instead."));
        }

        $this->salesDocumentRepository->delete($document);

        ActivityLogService::record('deleted', 'Deleted '.$this->describe($document), $document, ['source' => 'api']);
    }

    /**
     * The totals of a document with $header's discount, tax settings and
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

    /** What the log calls it: "invoice INV-00001", or "draft invoice for "Uddin Traders"". */
    public function describe(SalesDocument $document): string
    {
        $noun = $this->noun($document->type);

        return $document->number
            ? "{$noun} {$document->number}"
            : "draft {$noun} for \"".Str::limit($document->contact?->name ?? 'a customer', 100).'"';
    }

    private function noun(DocumentType $type): string
    {
        return Str::lower($type->label());
    }

    /**
     * The header fields as kept. An invoice's due date is the form's, or its
     * date plus the payment terms - the form's, the customer's or the
     * business's (Doc 11 section 14). A document in the base currency has an
     * exchange rate of 1.
     */
    private function header(Workspace $workspace, DocumentType $type, array $data): array
    {
        $discount = ($data['discount_type'] ?? null) && ! Decimal::isZero(isset($data['discount_value']) ? (string) $data['discount_value'] : null);
        $terms    = $type === DocumentType::Invoice ? $this->paymentTerms($workspace, $data) : null;

        return [
            'contact_id'           => $data['contact_id'] ?? null,
            'location_id'          => $data['location_id'] ?? null,
            'document_date'        => $data['document_date'],
            'due_date'             => $type === DocumentType::Invoice
                ? ($data['due_date'] ?? CarbonImmutable::parse($data['document_date'])->addDays($terms)->toDateString())
                : null,
            'expiry_date'          => $type->isOffer() ? ($data['expiry_date'] ?? null) : null,
            'payment_terms'        => $terms,
            'currency'             => $data['currency'],
            'exchange_rate'        => $data['currency'] === $workspace->base_currency ? '1' : (string) ($data['exchange_rate'] ?? '1'),
            'customer_reference'   => $data['customer_reference'] ?? null,
            'sales_reference'      => $data['sales_reference'] ?? null,
            'discount_type'        => $discount ? $data['discount_type'] : null,
            'discount_value'       => $discount ? (string) $data['discount_value'] : null,
            'notes'                => $data['notes'] ?? null,
            'terms'                => $data['terms'] ?? null,
            'payment_instructions' => $data['payment_instructions'] ?? null,
        ];
    }

    private function paymentTerms(Workspace $workspace, array $data): int
    {
        return (int) ($data['payment_terms']
            ?? ($data['contact_id'] ?? null ? $this->contactRepository->findFor($workspace, $data['contact_id'])?->customer_payment_terms : null)
            ?? $this->salesSettingRepository->forWorkspace($workspace)->payment_terms);
    }
}
