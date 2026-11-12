<?php

namespace App\Services\Api\Search;

use App\Enums\DocumentType;
use App\Models\User\Contact;
use App\Models\User\Expense;
use App\Models\User\ExpensePayment;
use App\Models\User\Item;
use App\Models\User\Purchase;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\SupplierPayment;
use App\Models\User\Workspace;
use App\Repositories\User\GlobalSearchRepository;
use App\Services\Reports\SalesFigures;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Global Search in the header (Doc 14 section 16). A term finds contacts by
 * name, phone or e-mail; items by name, SKU or barcode; sales documents,
 * purchases and expenses by number or reference; and payments by number or
 * reference. Each result has its type, name or number, status, date,
 * location and source app, and opens its record. Archived contacts and items
 * are marked.
 *
 * A result follows Doc 14 section 24's order. An app's records come while
 * the app is on, or off but used before, when they stay readable (see
 * EnsureAppActive). A kind comes only when the role may view it (Doc 12). A
 * transaction comes only from a location the user may use. No amount comes,
 * so nothing here depends on the values a role may see.
 */
class GlobalSearchService
{
    /** At most this many of each kind: enough to open the one wanted. The lists search further. */
    public const SHOWN = 5;

    public function __construct(
        protected GlobalSearchRepository $globalSearchRepository,
        protected WorkspaceAccess $access,
        protected SalesDocumentRules $salesDocumentRules,
        protected SalesFigures $salesFigures,
        protected BusinessCalendar $businessCalendar,
    ) {}

    /**
     * What $term finds in $workspace, kind by kind: contacts, items, sales
     * documents, purchases, expenses, then payments.
     *
     * @return list<array{group: string, type: string, title: string, subtitle: string|null, status: string, date: string|null, location: string|null, app: string, archived: bool, link: string}>
     */
    public function search(Workspace $workspace, string $term): array
    {
        $term      = trim($term);
        $apps      = WorkspaceApps::for($workspace);
        $locations = $this->access->locations();
        $timezone  = $this->businessCalendar->timezone($workspace);
        $sources   = [
            'sales'     => $apps->hasHistory('sales') ? ($apps->row('sales')?->app?->name ?? 'Sales') : null,
            'purchases' => $apps->hasHistory('purchases') ? ($apps->row('purchases')?->app?->name ?? 'Purchases') : null,
            'expenses'  => $apps->hasHistory('expenses') ? ($apps->row('expenses')?->app?->name ?? 'Expenses') : null,
        ];

        return [
            ...$this->contacts($workspace, $term, $timezone),
            ...$this->items($workspace, $term, $timezone),
            ...($sources['sales'] ? $this->sales($workspace, $term, $locations, $sources['sales']) : []),
            ...($sources['purchases'] && $this->access->can('purchases.purchases.view') ? $this->purchases($workspace, $term, $locations, $sources['purchases']) : []),
            ...($sources['expenses'] && $this->access->can('expenses.expenses.view') ? $this->expenses($workspace, $term, $locations, $sources['expenses']) : []),
            ...$this->payments($workspace, $term, $locations, $sources),
        ];
    }

    private function contacts(Workspace $workspace, string $term, string $timezone): array
    {
        if (! $this->access->can('contacts.view')) {
            return [];
        }

        return $this->globalSearchRepository->contacts($workspace, $term, self::SHOWN)->map(fn (Contact $contact) => $this->row(
            group: 'contacts',
            type: 'Contact',
            title: $contact->name,
            subtitle: $contact->trading_name ?: ($contact->email ?: $contact->phone),
            status: $contact->is_active ? 'active' : 'archived',
            date: $this->day($contact->created_at, $timezone),
            app: 'Contacts',
            link: "/contacts/{$contact->id}",
            archived: ! $contact->is_active,
        ))->all();
    }

    private function items(Workspace $workspace, string $term, string $timezone): array
    {
        if (! $this->access->can('items.view')) {
            return [];
        }

        return $this->globalSearchRepository->items($workspace, $term, self::SHOWN)->map(fn (Item $item) => $this->row(
            group: 'items',
            type: 'Item',
            title: $item->name,
            subtitle: $item->sku ? "SKU {$item->sku}" : ($item->barcode ? "Barcode {$item->barcode}" : null),
            status: $item->is_active ? 'active' : 'archived',
            date: $this->day($item->created_at, $timezone),
            app: 'Items',
            link: "/items/{$item->id}",
            archived: ! $item->is_active,
        ))->all();
    }

    /** Quotations, proforma invoices, invoices and credit notes, of the kinds the role may view. */
    private function sales(Workspace $workspace, string $term, ?array $locations, string $source): array
    {
        $types = $this->access->viewable(array_values(DocumentType::KINDS));

        if ($types === []) {
            return [];
        }

        $today = $this->businessCalendar->today($workspace)->toDateString();

        return $this->globalSearchRepository->salesDocuments($workspace, $term, $types, $locations, self::SHOWN)->map(fn (SalesDocument $document) => $this->row(
            group: 'sales',
            type: $document->type->label(),
            title: $document->number ?? 'Draft',
            subtitle: $this->salesFigures->customerName($document),
            status: $this->salesDocumentRules->status($document, $today),
            date: $this->day($document->document_date),
            app: $source,
            link: "/sales/{$document->type->kind()}/{$document->id}",
            location: $document->location?->name,
        ))->all();
    }

    private function purchases(Workspace $workspace, string $term, ?array $locations, string $source): array
    {
        return $this->globalSearchRepository->purchases($workspace, $term, $locations, self::SHOWN)->map(fn (Purchase $purchase) => $this->row(
            group: 'purchases',
            type: 'Purchase',
            title: $purchase->number ?? 'Draft',
            subtitle: $purchase->supplier_snapshot['name'] ?? $purchase->contact?->name,
            status: $purchase->status->value,
            date: $this->day($purchase->document_date),
            app: $source,
            link: "/purchases/{$purchase->id}",
            location: $purchase->location?->name,
        ))->all();
    }

    private function expenses(Workspace $workspace, string $term, ?array $locations, string $source): array
    {
        return $this->globalSearchRepository->expenses($workspace, $term, $locations, self::SHOWN)->map(fn (Expense $expense) => $this->row(
            group: 'expenses',
            type: 'Expense',
            title: $expense->number ?? 'Draft',
            subtitle: $expense->payeeName(),
            status: $expense->status->value,
            date: $this->day($expense->document_date),
            app: $source,
            link: "/expenses/{$expense->id}",
            location: $expense->location?->name,
        ))->all();
    }

    /**
     * Customer payments and refunds, supplier payments and expense payments
     * together, newest first: the kinds whose app has been used and whose
     * payments the role may view.
     *
     * @param  array<string, string|null>  $sources  each family's app, null when never used
     */
    private function payments(Workspace $workspace, string $term, ?array $locations, array $sources): array
    {
        $allowed = fn (string $family) => $sources[$family] !== null && $this->access->can("{$family}.payments.view");

        $customer = ($allowed('sales') ? $this->globalSearchRepository->salesPayments($workspace, $term, $locations, self::SHOWN) : collect())
            ->map(fn (SalesPayment $payment) => $this->payment($payment, $payment->kind === SalesPayment::REFUND ? 'Customer Refund' : 'Customer Payment', $sources['sales'], "/sales/payments/{$payment->id}"));

        $supplier = ($allowed('purchases') ? $this->globalSearchRepository->supplierPayments($workspace, $term, $locations, self::SHOWN) : collect())
            ->map(fn (SupplierPayment $payment) => $this->payment($payment, 'Supplier Payment', $sources['purchases'], "/purchases/payments/{$payment->id}"));

        $expense = ($allowed('expenses') ? $this->globalSearchRepository->expensePayments($workspace, $term, $locations, self::SHOWN) : collect())
            ->map(fn (ExpensePayment $payment) => $this->payment($payment, 'Expense Payment', $sources['expenses'], "/expenses/payments/{$payment->id}", $payment->expense?->payeeName()));

        return $customer->concat($supplier)->concat($expense)
            ->sortByDesc(fn (array $row) => [$row['date'], $row['order']])
            ->take(self::SHOWN)
            ->map(fn (array $row) => collect($row)->except('order')->all())
            ->values()
            ->all();
    }

    private function payment(SalesPayment|SupplierPayment|ExpensePayment $payment, string $type, string $source, string $link, ?string $payee = null): array
    {
        return [
            ...$this->row(
                group: 'payments',
                type: $type,
                title: $payment->number ?? $type,
                subtitle: $payment->contact?->name ?? $payee,
                status: (string) $payment->status,
                date: $this->day($payment->payment_date),
                app: $source,
                link: $link,
                location: $payment->location?->name,
            ),
            // What orders payments of one day: the newest made first.
            'order' => $payment->created_at?->getTimestamp() ?? 0,
        ];
    }

    private function row(string $group, string $type, string $title, ?string $subtitle, string $status, ?string $date, string $app, string $link, ?string $location = null, bool $archived = false): array
    {
        return compact('group', 'type', 'title', 'subtitle', 'status', 'date', 'location', 'app', 'archived', 'link');
    }

    /** A date as Y-m-d: a day stored as one, or a moment on its business day in $timezone. */
    private function day(mixed $value, ?string $timezone = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return substr($value, 0, 10);
        }

        $moment = Carbon::instance($value);

        return ($timezone ? $moment->setTimezone($timezone) : $moment)->toDateString();
    }
}
