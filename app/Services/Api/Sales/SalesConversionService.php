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

/**
 * Turning a quotation into a proforma invoice or an invoice, or a proforma
 * into an invoice (Doc 08 section 11). The new document is a draft dated
 * today, with the customer, lines, prices, discounts, taxes, currency and
 * exchange rate, notes and terms copied as they were; the two stay linked, and
 * the one it came from is marked converted - never rewritten.
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
    ) {}

    /** The business's today, which the new draft is dated and its status read by. */
    public function today(Workspace $workspace): string
    {
        return $this->salesDocumentService->today($workspace);
    }

    public function convert(Workspace $workspace, DocumentType $from, int $id, DocumentType $to, bool $again, User $user): SalesDocument
    {
        $source = $this->salesDocumentService->find($workspace, $from, $id);
        $today  = $this->salesDocumentService->today($workspace);

        if (! $this->salesDocumentRules->allows($source, "convert_to_{$to->value}", $today)) {
            $noun = $this->noun($to);

            $this->refuse("This {$this->noun($from)} cannot become ".(str_starts_with($noun, 'i') ? 'an' : 'a')." {$noun}.");
        }

        if ($source->status === SalesDocumentStatus::Converted && ! $again) {
            $this->refuse("This {$this->noun($from)} was converted before. Convert it again only if you mean to make another document from it.");
        }

        $header = $this->header($workspace, $source, $to, $today, $user);

        [$totals, $rows] = $this->salesDocumentService->totals(
            $header,
            $source->lines->map(fn (SalesDocumentLine $line) => $line->only(self::LINE_FIELDS))->all(),
        );

        $target = DB::transaction(function () use ($workspace, $source, $header, $totals, $rows, $user) {
            $target = $this->salesDocumentRepository->create($workspace, [...$header, ...$totals]);

            $this->salesDocumentRepository->replaceLines($target, $rows);
            $this->salesDocumentRepository->update($source, ['status' => SalesDocumentStatus::Converted, 'updated_by' => $user->id]);

            return $target;
        });

        ActivityLogService::record('created', 'Converted '.$this->salesDocumentService->describe($source)." to a draft {$this->noun($to)}", $target, ['source' => 'api']);

        return $this->salesDocumentRepository->loadShown($target);
    }

    /**
     * The new draft's header: the source's, dated today - an invoice due by
     * the customer's payment terms (or the business's), a proforma valid for
     * Settings > Sales' days.
     */
    private function header(Workspace $workspace, SalesDocument $source, DocumentType $to, string $today, User $user): array
    {
        $settings = $this->salesSettingRepository->forWorkspace($workspace);
        $terms    = $source->contact?->customer_payment_terms ?? $settings->payment_terms;
        $days     = fn (int $days) => CarbonImmutable::parse($today)->addDays($days)->toDateString();

        return [
            ...$source->only([
                'contact_id', 'location_id', 'currency', 'exchange_rate', 'price_tax_mode', 'tax_rounding', 'customer_reference',
                'sales_reference', 'discount_type', 'discount_value', 'notes', 'terms', 'payment_instructions',
            ]),
            'type'          => $to,
            'document_date' => $today,
            'due_date'      => $to === DocumentType::Invoice ? $days($terms) : null,
            'payment_terms' => $to === DocumentType::Invoice ? $terms : null,
            'expiry_date'   => $to === DocumentType::Proforma ? $days($settings->proforma_validity) : null,
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
