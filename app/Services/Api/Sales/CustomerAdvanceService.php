<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Enums\Edition;
use App\Enums\SalesDocumentStatus;
use App\Models\User\CustomerAdvance;
use App\Models\User\PaymentAccount;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Shared\RowLockRepository;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\CustomerAdvanceRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\SalesAllocationRepository;
use App\Repositories\User\SalesSettingRepository;
use App\Services\Api\PaymentAccounts\AccountLedger;
use App\Services\Documents\DocumentNumbers;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer advances (Doc 16 section 16.15), where Settings > Sales has them
 * on: recorded from the customer or a sales order, or kept from a payment over
 * its invoice once the user confirmed it (section 16.16). An advance is money
 * in - its account's history shows it - but no sale: allocations apply it to
 * the customer's issued invoices later (SalesAllocationService). It is
 * reversed, never edited or deleted, and only once nothing of it is applied.
 */
class CustomerAdvanceService
{
    use RefusesConflicts;

    public function __construct(
        protected CustomerAdvanceRepository $advances,
        protected SalesAllocationRepository $allocations,
        protected PaymentAccountRepository $paymentAccountRepository,
        protected ContactRepository $contactRepository,
        protected SalesSettingRepository $salesSettingRepository,
        protected SalesDocumentService $salesDocumentService,
        protected AccountLedger $accountLedger,
        protected DocumentNumbers $documentNumbers,
        protected WorkspaceAccess $access,
        protected RowLockRepository $rows,
    ) {}

    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->advances->paginateFor($workspace, [...$filters, 'location_ids' => $this->access->locations()], $perPage);
    }

    /** Another business's advance - or one at a location out of reach - answers like one that does not exist. */
    public function find(Workspace $workspace, int $id): CustomerAdvance
    {
        $advance   = $this->advances->findFor($workspace, $id);
        $locations = $this->access->locations();

        if (! $advance || ($advance->location_id && $locations !== null && ! in_array($advance->location_id, $locations, true))) {
            abort(404, 'Advance not found.');
        }

        return $advance;
    }

    /** Whether the business takes advances at all: the full Sales app, with them on in Settings > Sales. */
    public function takesAdvances(Workspace $workspace): bool
    {
        return WorkspaceApps::for($workspace)->has('sales', Edition::Full)
            && (bool) ($this->salesSettingRepository->forWorkspace($workspace)->customer_advances ?? true);
    }

    /** Whether this user may keep a payment's extra as an advance - what the payment dialog offers. */
    public function keepsOverpayments(Workspace $workspace): bool
    {
        return $this->access->can('sales.advances.record') && $this->takesAdvances($workspace);
    }

    /**
     * $data is validated (see RecordAdvanceRequest): from a customer
     * (contact_id) in the currency chosen, or from one of their sales orders
     * still open (sales_order_id), in its currency and at its location.
     */
    public function record(Workspace $workspace, User $user, array $data): CustomerAdvance
    {
        if (! $this->takesAdvances($workspace)) {
            $this->refuse('Customer advances are off. Turn them on in Settings > Sales first.');
        }

        $order = empty($data['sales_order_id']) ? null : $this->salesDocumentService->find($workspace, DocumentType::SalesOrder, (int) $data['sales_order_id']);

        if ($order && ! in_array($order->status, [SalesDocumentStatus::Draft, SalesDocumentStatus::Confirmed, SalesDocumentStatus::OnHold], true)) {
            $this->refuse('An advance is taken for an order still open: this one is '.str_replace('_', ' ', $order->status->value).'.');
        }

        $contact = $this->contactRepository->findFor($workspace, (int) ($order?->contact_id ?? $data['contact_id'] ?? 0));

        if (! $contact?->isCustomer()) {
            throw ValidationException::withMessages(['contact_id' => 'Choose the customer who paid.']);
        }

        $account  = $this->paymentAccountRepository->findFor($workspace, (int) $data['payment_account_id']);
        $currency = $order?->currency ?? $data['currency'];
        $amount   = Decimal::round((string) $data['amount']);

        $this->accountLedger->checkMove($account, $data['payment_date'], $this->salesDocumentService->today($workspace));

        if ($order && $data['payment_date'] < $order->document_date) {
            throw ValidationException::withMessages(['payment_date' => "An advance for this order cannot come before it, dated {$order->document_date}."]);
        }

        $intoAccount = $account->currency === $currency ? $amount : $this->intoAccount($data);

        $advance = DB::transaction(fn () => $this->make($workspace, $user, $account, [
            'contact_id'        => $contact->id,
            'sales_document_id' => $order?->id,
            'location_id'       => $order?->location_id,
            'payment_date'      => $data['payment_date'],
            'method'            => $data['method'] ?? null,
            'reference'         => $data['reference'] ?? null,
            'notes'             => $data['notes'] ?? null,
            'currency'          => $currency,
            'amount'            => $amount,
            'account_amount'    => $intoAccount,
        ], null));

        $for = $order ? " for sales order {$order->number}" : '';

        ActivityLogService::record('created', "Recorded advance {$advance->number} of {$currency} {$amount} from {$contact->name}{$for}", $advance, ['source' => 'api']);

        return $this->advances->loadShown($advance);
    }

    /**
     * What a payment brought beyond its invoice's balance, kept as the
     * customer's advance in the invoice's currency (section 16.16) - inside
     * the payment's transaction, once the user confirmed it.
     */
    public function keepExtra(Workspace $workspace, User $user, PaymentAccount $account, SalesPayment $payment, SalesDocument $invoice, string $extra, string $intoAccount): CustomerAdvance
    {
        $advance = $this->make($workspace, $user, $account, [
            'contact_id'        => $invoice->contact_id,
            'sales_document_id' => $invoice->id,
            'sales_payment_id'  => $payment->id,
            'location_id'       => $invoice->location_id,
            'payment_date'      => $payment->payment_date,
            'method'            => $payment->method,
            'reference'         => $payment->reference,
            'notes'             => "Paid over invoice {$invoice->number} with payment {$payment->number}.",
            'currency'          => $invoice->currency,
            'amount'            => $extra,
            'account_amount'    => $intoAccount,
        ], (string) $invoice->exchange_rate);

        ActivityLogService::record('created', "Kept {$invoice->currency} {$extra} paid over invoice {$invoice->number} as advance {$advance->number}", $advance, ['source' => 'api']);

        return $advance;
    }

    /** Taken back (a mistake, or the money went back to the customer): it stays on record and leaves the account's balance. */
    public function reverse(Workspace $workspace, int $id, User $user, ?string $reason): CustomerAdvance
    {
        $advance = $this->find($workspace, $id);

        DB::transaction(function () use ($advance, $user, $reason) {
            $advance = $this->rows->lock($advance);

            if (! $advance->isCompleted()) {
                $this->refuse('This advance was reversed already.');
            }

            if ($this->allocations->hasActiveFromAdvance($advance)) {
                $this->refuse('Some of this advance is applied to invoices. Take those off first.');
            }

            $this->advances->update($advance, [
                'status'          => CustomerAdvance::REVERSED,
                'balance'         => '0.00',
                'reversed_at'     => now(),
                'reversed_by'     => $user->id,
                'reversal_reason' => $reason,
            ]);

            $this->accountLedger->setStatus($advance, CustomerAdvance::REVERSED);
        });

        ActivityLogService::record('updated', "Reversed advance {$advance->number}", $advance, ['source' => 'api']);

        return $this->advances->loadShown($advance->refresh());
    }

    /** Its applied amount and balance, counted again from its active allocations. */
    public function refresh(CustomerAdvance $advance): void
    {
        $applied = $this->allocations->sumFromAdvance($advance);

        $this->advances->update($advance, [
            'allocated_amount' => $applied,
            'balance'          => $advance->isCompleted() ? Decimal::max(bcsub((string) $advance->amount, $applied, 2), '0.00') : '0.00',
        ]);
    }

    /** Numbered, written and in its account's history. $fallbackRate is base per unit of its currency, when known. */
    private function make(Workspace $workspace, User $user, PaymentAccount $account, array $data, ?string $fallbackRate): CustomerAdvance
    {
        $base = $this->accountLedger->baseAmount($workspace, $data['account_amount'], $account->currency, $data['amount'], $data['currency'], $data['payment_date'], $fallbackRate);

        if ($base === null) {
            throw ValidationException::withMessages(['currency' => "There is no rate for {$data['currency']} or {$account->currency} on {$data['payment_date']}. Add one in Settings > Currencies first."]);
        }

        $advance = $this->advances->create($workspace, [
            ...$data,
            'number'             => $this->documentNumbers->next(
                $workspace,
                DocumentType::CustomerAdvance,
                CarbonImmutable::parse($data['payment_date']),
                fn (string $number) => $this->advances->numberTaken($workspace, $number),
                $data['location_id'],
            ),
            'payment_account_id' => $account->id,
            'exchange_rate'      => bcdiv($data['account_amount'], $data['amount'], 8),
            'base_amount'        => $base,
            'allocated_amount'   => '0.00',
            'balance'            => $data['amount'],
            'created_by'         => $user->id,
        ]);

        $this->accountLedger->recordAdvance($workspace, $advance);

        return $advance;
    }

    /** What reached an account in another currency: entered with the advance. */
    private function intoAccount(array $data): string
    {
        if (empty($data['account_amount'])) {
            throw ValidationException::withMessages(['account_amount' => 'Enter what reached the account, in its currency.']);
        }

        return Decimal::round((string) $data['account_amount']);
    }
}
