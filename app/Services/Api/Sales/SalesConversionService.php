<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\SalesSettingRepository;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\ActivityLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turning a quotation into a proforma invoice or an invoice, or a proforma
 * into an invoice (Doc 08 section 11) - and, with the full Sales app, either
 * into a sales order, which makes proformas and invoices of its own (Doc 16
 * Phase B). The new document is a draft dated today, with the customer,
 * lines, prices, discounts, taxes, currency and exchange rate, notes and
 * terms copied as they were; the two stay linked, line by line, and the one
 * it came from is marked converted - never rewritten. A sales order stays as
 * it is.
 *
 * Converting one that was converted before is refused unless asked for again
 * on purpose ($again), so the same quotation does not become two invoices by a
 * double click. Nothing is a sale until the invoice is issued.
 */
class SalesConversionService
{
    use RefusesConflicts;

    /** What a line keeps across a conversion - everything but its amounts, which are worked out again. */
    private const LINE_FIELDS = [
        'position', 'item_id', 'name', 'description', 'quantity', 'unit', 'unit_price', 'discount_type', 'discount_value',
        'tax_rate_id', 'tax_name', 'tax_code', 'tax_rate', 'tax_treatment', 'components', 'warranty',
    ];

    public function __construct(
        protected SalesDocumentRepository $salesDocumentRepository,
        protected SalesSettingRepository $salesSettingRepository,
        protected SalesDocumentService $salesDocumentService,
        protected SalesDocumentRules $salesDocumentRules,
        protected SalesOrderInvoicing $salesOrderInvoicing,
    ) {}

    /**
     * A draft invoice of a confirmed sales order's $quantities - order line id
     * => quantity (Doc 16 sections 16.11 and 16.12): the order's customer,
     * currency, terms, addresses and people, each line linked to the order's.
     * Beyond what a line has left needs the permission and $reason, which the
     * invoice keeps (SalesOrderInvoicing::check). The order stays as it is.
     */
    public function invoiceOrder(Workspace $workspace, int $id, array $quantities, User $user, ?string $reason): SalesDocument
    {
        $order = $this->salesDocumentService->find($workspace, DocumentType::SalesOrder, $id);
        $today = $this->salesDocumentService->today($workspace);

        if (! $this->salesDocumentRules->allows($order, 'create_invoice', $today)) {
            $this->refuse('Only a confirmed sales order is invoiced. Resume one on hold first.');
        }

        $chosen = array_filter(
            array_intersect_key($quantities, array_flip($order->lines->pluck('id')->all())),
            fn ($quantity) => bccomp((string) $quantity, '0', 3) > 0,
        );

        if ($chosen === []) {
            throw ValidationException::withMessages(['lines' => 'Choose how much of at least one line to invoice.']);
        }

        $beyond = $this->salesOrderInvoicing->check($workspace, $order, $chosen, $reason);
        $header = [...$this->header($workspace, $order, DocumentType::Invoice, $today, $user), 'over_invoice_reason' => $beyond ? $reason : null];

        [$totals, $rows] = $this->salesDocumentService->totals($header, $order->lines
            ->filter(fn (SalesDocumentLine $line) => isset($chosen[$line->id]))
            ->values()
            ->map(fn (SalesDocumentLine $line, int $index) => [
                ...$line->only(self::LINE_FIELDS), 'position' => $index + 1, 'quantity' => (string) $chosen[$line->id], 'source_line_id' => $line->id,
            ])
            ->all());

        $invoice = DB::transaction(function () use ($workspace, $header, $totals, $rows) {
            $invoice = $this->salesDocumentRepository->create($workspace, [...$header, ...$totals]);

            $this->salesDocumentRepository->replaceLines($invoice, $rows);

            return $invoice;
        });

        ActivityLogService::record('created', 'Made a draft invoice from '.$this->salesDocumentService->describe($order).($beyond ? " beyond what it had left: {$reason}" : ''), $invoice, ['source' => 'api']);

        return $this->salesDocumentRepository->loadShown($invoice);
    }

    /** The business's today, which the new draft is dated and its status read by. */
    public function today(Workspace $workspace): string
    {
        return $this->salesDocumentService->today($workspace);
    }

    public function convert(Workspace $workspace, DocumentType $from, int $id, DocumentType $to, bool $again, User $user): SalesDocument
    {
        $source = $this->salesDocumentService->find($workspace, $from, $id);
        $today  = $this->salesDocumentService->today($workspace);
        // A sales order makes documents and stays as it is (Doc 16 section 16.9); the others become them.
        $order  = $from === DocumentType::SalesOrder;

        if (! $this->salesDocumentRules->allows($source, ($order ? 'create_' : 'convert_to_').$to->value, $today)) {
            $noun = $this->noun($to);

            $this->refuse("This {$this->noun($from)} cannot become ".(str_starts_with($noun, 'i') ? 'an' : 'a')." {$noun}.");
        }

        // An order's invoices are made of the quantities chosen (invoiceOrder), never a copy of it all.
        if ($order && $to === DocumentType::Invoice) {
            $this->refuse('A sales order is invoiced from its Create Invoice, line by line.');
        }

        if ($source->status === SalesDocumentStatus::Converted && ! $again) {
            $this->refuse("This {$this->noun($from)} was converted before. Convert it again only if you mean to make another document from it.");
        }

        $header = $this->header($workspace, $source, $to, $today, $user);

        // Each line linked to the one it came from (Doc 16 section 16.28).
        [$totals, $rows] = $this->salesDocumentService->totals(
            $header,
            $source->lines->map(fn (SalesDocumentLine $line) => [...$line->only(self::LINE_FIELDS), 'source_line_id' => $line->id])->all(),
        );

        $target = DB::transaction(function () use ($workspace, $source, $header, $totals, $rows, $user, $order) {
            $target = $this->salesDocumentRepository->create($workspace, [...$header, ...$totals]);

            $this->salesDocumentRepository->replaceLines($target, $rows);

            if (! $order) {
                $this->salesDocumentRepository->update($source, ['status' => SalesDocumentStatus::Converted, 'updated_by' => $user->id]);
            }

            return $target;
        });

        ActivityLogService::record('created', ($order ? 'Made a draft '.$this->noun($to).' from ' : 'Converted ').$this->salesDocumentService->describe($source).($order ? '' : " to a draft {$this->noun($to)}"), $target, ['source' => 'api']);

        return $this->salesDocumentRepository->loadShown($target);
    }

    /**
     * A sales order copied as a new draft dated today (Doc 16 section 16.9's
     * Duplicate): the same customer, lines, prices and terms, linked to
     * nothing - an order of its own.
     */
    public function duplicate(Workspace $workspace, DocumentType $type, int $id, User $user): SalesDocument
    {
        $source = $this->salesDocumentService->find($workspace, $type, $id);
        $today  = $this->salesDocumentService->today($workspace);

        if (! $this->salesDocumentRules->allows($source, 'duplicate', $today)) {
            $this->refuse("This {$this->noun($type)} cannot be duplicated.");
        }

        $header = [...$this->header($workspace, $source, $type, $today, $user), 'source_id' => null];

        [$totals, $rows] = $this->salesDocumentService->totals(
            $header,
            $source->lines->map(fn (SalesDocumentLine $line) => $line->only(self::LINE_FIELDS))->all(),
        );

        $copy = DB::transaction(function () use ($workspace, $header, $totals, $rows) {
            $copy = $this->salesDocumentRepository->create($workspace, [...$header, ...$totals]);

            $this->salesDocumentRepository->replaceLines($copy, $rows);

            return $copy;
        });

        ActivityLogService::record('created', 'Duplicated '.$this->salesDocumentService->describe($source).' as a new draft', $copy, ['source' => 'api']);

        return $this->salesDocumentRepository->loadShown($copy);
    }

    /**
     * The new draft's header: the source's, dated today - an invoice due by
     * the payment terms of the sales order it came from, else the customer's
     * (or the business's), a sales order with those terms for its invoices, a
     * quotation or proforma valid for Settings > Sales' days.
     */
    private function header(Workspace $workspace, SalesDocument $source, DocumentType $to, string $today, User $user): array
    {
        $settings = $this->salesSettingRepository->forWorkspace($workspace);
        $terms    = ($source->type === DocumentType::SalesOrder ? $source->payment_terms : null) ?? $source->contact?->customer_payment_terms ?? $settings->payment_terms;
        $days     = fn (int $days) => CarbonImmutable::parse($today)->addDays($days)->toDateString();
        $termed   = in_array($to, [DocumentType::Invoice, DocumentType::SalesOrder], true);

        return [
            ...$source->only([
                'contact_id', 'location_id', 'currency', 'exchange_rate', 'exchange_rate_date', 'exchange_rate_source', 'price_tax_mode', 'tax_rounding', 'customer_reference',
                'sales_reference', 'discount_type', 'discount_value', 'notes', 'internal_notes', 'terms', 'payment_instructions',
                // The full Sales app's (Doc 16 Phase A): who sold it, where it goes and when.
                ...SalesDocument::FULL_FIELDS,
            ]),
            'type'          => $to,
            'document_date' => $today,
            'due_date'      => $to === DocumentType::Invoice ? $days($terms) : null,
            'payment_terms' => $termed ? $terms : null,
            'expiry_date'   => $to->isOffer() ? $days($to === DocumentType::Quotation ? $settings->quotation_validity : $settings->proforma_validity) : null,
            'source_id'     => $source->id,
            'created_by'    => $user->id,
            'updated_by'    => $user->id,
        ];
    }

    private function noun(DocumentType $type): string
    {
        return strtolower($type->label());
    }
}
