<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Enums\Edition;
use App\Models\User\Contact;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\CustomerCreditRepository;
use App\Repositories\User\SalesSettingRepository;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\Sales\OutstandingInvoicesReport;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * A customer's credit control (Doc 16 section 16.21 - Phase G1), the full
 * Sales app's.
 *
 * - Their settings: a credit limit in the base currency (none for no limit);
 *   whether they may buy on credit at all - when not, their limit is nothing;
 *   whether going over warns or blocks; and a note shown when a sale is made
 *   for them. Changing them needs MANAGE.
 * - Their exposure, in the base currency: what their issued invoices still
 *   owe, as Outstanding Invoices has it; with Settings > Sales's
 *   credit_counts_orders, what their confirmed and held orders have not
 *   invoiced yet; less the credit they hold unapplied - advances, and credit
 *   notes with credit left.
 * - The check: an invoice issued, or - while orders count - an order
 *   confirmed, that adds to the exposure and takes it over the limit warns,
 *   or is refused unless someone with OVERRIDE gives a reason. What adds
 *   nothing goes through - an invoice its deposits cover, one billing an
 *   order already counted, a sale paid ahead: "A credit limit does not
 *   affect fully paid cash sales".
 */
class CustomerCredit
{
    public const MANAGE = 'sales.credit.manage';

    public const OVERRIDE = 'sales.credit.override';

    public function __construct(
        protected ContactRepository $contacts,
        protected CustomerCreditRepository $credit,
        protected SalesSettingRepository $settings,
        protected OutstandingInvoicesReport $outstanding,
        protected ReportPeriod $period,
        protected WorkspaceAccess $access,
    ) {}

    /** A customer's settings and exposure, for their page and the sales form. */
    public function summary(Workspace $workspace, int $contactId): array
    {
        return $this->figures($workspace, $this->customer($workspace, $contactId));
    }

    /**
     * Every customer's figures at once, as summary() gives them, with their
     * name, by their id - those who owe, hold credit or have orders that
     * count, and any with a credit limit: Customer Credit Exposure's (Doc 16
     * Phase H3c). Each read once for all of them.
     *
     * @return array<int, array>
     */
    public function exposures(Workspace $workspace): array
    {
        $counts   = $this->countsOrders($workspace);
        $owed     = collect($this->outstanding->rows($workspace, $this->period->today($workspace), []))->groupBy('contact_id');
        $orders   = $counts ? $this->credit->openOrdersFor($workspace, null)->groupBy('contact_id') : collect();
        $advances = $this->credit->openAdvancesFor($workspace, null)->groupBy('contact_id');
        $notes    = $this->credit->openCreditNotesFor($workspace, null)->groupBy('contact_id');
        $ids      = collect([$owed, $orders, $advances, $notes])->flatMap(fn (Collection $held) => $held->keys())->filter()->unique()->values()->all();
        $figures  = [];

        foreach ($this->contacts->creditCustomers($workspace, $ids) as $customer) {
            $exposure = $this->exposureOf(
                ($owed[$customer->id] ?? collect())->all(),
                $orders[$customer->id] ?? collect(),
                $advances[$customer->id] ?? collect(),
                $notes[$customer->id] ?? collect(),
                $counts,
            );

            $figures[$customer->id] = ['name' => $customer->name, ...$this->figuresOf($workspace, $customer, $counts, $exposure)];
        }

        return $figures;
    }

    /** A customer's settings changed - the limit, credit sales, what going over does, the note - and logged when they differ. */
    public function save(Workspace $workspace, int $contactId, array $data): array
    {
        $customer = $this->customer($workspace, $contactId);
        $before   = $this->describe($workspace, $customer);

        $this->contacts->update($customer, [
            'credit_limit'   => filled($data['credit_limit'] ?? null) ? Decimal::round((string) $data['credit_limit']) : null,
            'credit_sales'   => (bool) $data['credit_sales'],
            'credit_action'  => $data['credit_action'],
            'credit_warning' => filled($data['credit_warning'] ?? null) ? trim($data['credit_warning']) : null,
        ]);

        $after = $this->describe($workspace, $customer);

        if ($after !== $before) {
            ActivityLogService::record('updated', "Changed the credit control of {$customer->name}: {$after}", $customer, ['source' => 'api']);
        }

        return $this->figures($workspace, $customer);
    }

    /**
     * Whether $document - an invoice being issued, at $rate when given, or a
     * sales order being confirmed - may go ahead for its customer.
     *
     * Within the limit, or adding nothing: null. Over it, for a customer who
     * warns: what to tell the user. For one who blocks: refused on
     * credit_reason, unless $reason comes from someone who may override -
     * then it goes ahead, `overridden`, for the caller to keep the reason and
     * log it (logOverride).
     *
     * @return ?array{message: string, overridden: bool}
     */
    public function check(Workspace $workspace, SalesDocument $document, ?string $reason, ?string $rate = null): ?array
    {
        if (! $document->contact_id || ! WorkspaceApps::for($workspace)->has('sales', Edition::Full)) {
            return null;
        }

        $customer = $this->contacts->findFor($workspace, (int) $document->contact_id);

        if (! $customer || $this->limitOf($customer) === null) {
            return null;
        }

        $counts = $this->countsOrders($workspace);
        $orders = $counts ? $this->credit->openOrdersFor($workspace, $customer->id) : collect();

        return $this->against($workspace, $customer, $this->adds($document, $rate, $orders, $counts), $orders, $counts, $reason);
    }

    /**
     * As check(), for a sale adding $adds - in the base currency - to
     * $customer's exposure: a POS sale left partly on their account (Doc 18
     * Phase D3a), where only what its payments leave unpaid adds.
     *
     * @return ?array{message: string, overridden: bool}
     */
    public function checkAdding(Workspace $workspace, Contact $customer, string $adds, ?string $reason): ?array
    {
        if (! WorkspaceApps::for($workspace)->has('sales', Edition::Full) || $this->limitOf($customer) === null) {
            return null;
        }

        $counts = $this->countsOrders($workspace);
        $orders = $counts ? $this->credit->openOrdersFor($workspace, $customer->id) : collect();

        return $this->against($workspace, $customer, $adds, $orders, $counts, $reason);
    }

    /** $adds against $customer's limit: within it, or adding nothing, null; over it, a warning - or refused, or overridden with $reason. */
    private function against(Workspace $workspace, Contact $customer, string $adds, Collection $orders, bool $counts, ?string $reason): ?array
    {
        $limit = $this->limitOf($customer);

        if (bccomp($adds, '0', 2) <= 0) {
            return null;
        }

        $after = bcadd($this->exposure($workspace, $customer, $orders, $counts)['exposure'], $adds, 2);

        if (bccomp($after, $limit, 2) <= 0) {
            return null;
        }

        if ($customer->credit_action !== Contact::CREDIT_BLOCK) {
            return ['message' => $this->over($workspace, $customer, $after, true), 'overridden' => false];
        }

        $mayOverride = $this->access->can(self::OVERRIDE);

        if (! $mayOverride || ! filled($reason)) {
            throw ValidationException::withMessages(['credit_reason' => $this->over($workspace, $customer, $after, false).' '.($mayOverride
                ? 'To go on, give a reason.'
                : 'Someone who may override credit limits can go on, with a reason.')]);
        }

        return ['message' => $this->over($workspace, $customer, $after, true), 'overridden' => true];
    }

    /** A sale over the limit that someone allowed to went on with ($what: "Issued invoice INV-00003"): in the Activity Log, with why. */
    public function logOverride(SalesDocument $document, string $what, string $reason): void
    {
        ActivityLogService::record('updated', "{$what} over its customer's credit limit, because: ".trim($reason), $document, ['source' => 'api']);
    }

    private function customer(Workspace $workspace, int $contactId): Contact
    {
        return $this->contacts->findFor($workspace, $contactId) ?? abort(404, 'Contact not found.');
    }

    /** The most they may owe, in the base currency: nothing when they may not buy on credit, null for no limit. */
    private function limitOf(Contact $customer): ?string
    {
        if (! $customer->credit_sales) {
            return '0.00';
        }

        return $customer->credit_limit === null ? null : Decimal::round((string) $customer->credit_limit);
    }

    private function countsOrders(Workspace $workspace): bool
    {
        return (bool) $this->settings->forWorkspace($workspace)->credit_counts_orders;
    }

    /** What $document adds to its customer's exposure, in the base currency. */
    private function adds(SalesDocument $document, ?string $rate, Collection $orders, bool $counts): string
    {
        if ($document->type === DocumentType::SalesOrder) {
            return $counts ? Decimal::round(Decimal::of($document->base_grand_total)) : '0.00';
        }

        $base  = Decimal::round(bcmul(Decimal::of($document->grand_total), Decimal::of($rate ?? $document->exchange_rate), 10));
        $order = $counts && $document->source_id ? $orders->firstWhere('id', $document->source_id) : null;

        // Billing an order already counted moves what is owed from the order to the invoice.
        return $order ? Decimal::max(bcsub($base, $this->uninvoiced($order), 2), '0.00') : $base;
    }

    /**
     * @param  Collection<int, SalesDocument>  $orders  the open orders, with what their invoices billed - when they count
     * @return array{outstanding: string, overdue: string, orders: string, unapplied: string, exposure: string}
     */
    private function exposure(Workspace $workspace, Contact $customer, Collection $orders, bool $counts): array
    {
        return $this->exposureOf(
            $this->outstanding->rows($workspace, $this->period->today($workspace), ['contact_id' => $customer->id]),
            $orders,
            $this->credit->openAdvancesFor($workspace, $customer->id),
            $this->credit->openCreditNotesFor($workspace, $customer->id),
            $counts,
        );
    }

    /**
     * The same from what was read already: the invoices still owing ($owed,
     * as Outstanding Invoices' rows), the open orders, and the advances and
     * credit notes with credit left.
     *
     * @return array{outstanding: string, overdue: string, orders: string, unapplied: string, exposure: string}
     */
    private function exposureOf(array $owed, Collection $orders, Collection $advances, Collection $notes, bool $counts): array
    {
        $owing     = Decimal::sum(array_column($owed, 'base_outstanding'));
        $ordered   = $counts ? Decimal::sum($orders->map(fn (SalesDocument $order) => $this->uninvoiced($order))->all()) : '0.00';
        $unapplied = Decimal::sum([
            // An advance's rate is to its account's currency: its base value is its share of base_amount.
            ...$advances->map(fn ($advance) => Decimal::isZero(Decimal::of($advance->amount))
                ? '0.00'
                : Decimal::round(bcdiv(bcmul(Decimal::of($advance->balance), Decimal::of($advance->base_amount), 10), Decimal::of($advance->amount), 10)))->all(),
            ...$notes->map(fn (SalesDocument $note) => Decimal::round(bcmul(Decimal::of($note->balance_due), Decimal::of($note->exchange_rate), 10)))->all(),
        ]);

        return [
            'outstanding' => $owing,
            'overdue'     => Decimal::sum(array_column(array_filter($owed, fn (array $row) => $row['days_overdue'] > 0), 'base_outstanding')),
            'orders'      => $ordered,
            'unapplied'   => $unapplied,
            'exposure'    => bcsub(bcadd($owing, $ordered, 2), $unapplied, 2),
        ];
    }

    /** What an open order has not invoiced yet, in the base currency. */
    private function uninvoiced(SalesDocument $order): string
    {
        return Decimal::max(bcsub(Decimal::of($order->base_grand_total), Decimal::of($order->invoiced_base), 2), '0.00');
    }

    private function figures(Workspace $workspace, Contact $customer): array
    {
        $counts = $this->countsOrders($workspace);

        return $this->figuresOf($workspace, $customer, $counts, $this->exposure($workspace, $customer, $counts ? $this->credit->openOrdersFor($workspace, $customer->id) : collect(), $counts));
    }

    /** A customer's settings, their $exposure, and what is left of their limit. */
    private function figuresOf(Workspace $workspace, Contact $customer, bool $counts, array $exposure): array
    {
        $limit = $this->limitOf($customer);

        return [
            'currency'       => $workspace->base_currency,
            'credit_limit'   => $customer->credit_limit === null ? null : Decimal::round((string) $customer->credit_limit),
            'credit_sales'   => (bool) $customer->credit_sales,
            'credit_action'  => $customer->credit_action,
            'credit_warning' => $customer->credit_warning,
            'counts_orders'  => $counts,
            ...$exposure,
            'available'      => $limit === null ? null : bcsub($limit, $exposure['exposure'], 2),
            'over'           => $limit !== null && bccomp($exposure['exposure'], $limit, 2) > 0,
        ];
    }

    /** "Acme has a credit limit of USD 5,000.00, and this takes their balance to USD 5,200.00." */
    private function over(Workspace $workspace, Contact $customer, string $after, bool $done): string
    {
        $balance = $this->money($workspace, $after);

        return $customer->credit_sales
            ? "{$customer->name} has a credit limit of {$this->money($workspace, (string) $customer->credit_limit)}, and this ".($done ? 'takes' : 'would take')." their balance to {$balance}."
            : "{$customer->name} buys only with payment ahead, and this ".($done ? 'leaves' : 'would leave')." {$balance} on credit.";
    }

    /** "limit USD 5,000.00, buys on credit, going over blocks" - for the Activity Log. */
    private function describe(Workspace $workspace, Contact $customer): string
    {
        return implode(', ', array_filter([
            $customer->credit_limit === null ? 'no limit' : 'limit '.$this->money($workspace, (string) $customer->credit_limit),
            $customer->credit_sales ? 'buys on credit' : 'buys only with payment ahead',
            $customer->credit_action === Contact::CREDIT_BLOCK ? 'going over blocks' : 'going over warns',
            $customer->credit_warning ? "note \"{$customer->credit_warning}\"" : null,
        ]));
    }

    private function money(Workspace $workspace, string $amount): string
    {
        return $workspace->base_currency.' '.number_format((float) $amount, 2);
    }
}
