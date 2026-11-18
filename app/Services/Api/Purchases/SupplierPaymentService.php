<?php

namespace App\Services\Api\Purchases;

use App\Enums\DocumentType;
use App\Models\User\PaymentAccount;
use App\Models\User\Purchase;
use App\Models\User\SupplierPayment;
use App\Models\User\User;
use App\Models\User\WithholdingTax;
use App\Models\User\Workspace;
use App\Repositories\Shared\RowLockRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\SupplierPaymentRepository;
use App\Repositories\User\WithholdingTaxRepository;
use App\Services\Api\PaymentAccounts\AccountLedger;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Documents\DocumentNumbers;
use App\Services\Documents\DocumentTemplates;
use App\Services\Purchases\PurchaseRules;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Supplier payments (Doc 09 sections 12 and 13): recorded against a recorded
 * purchase, numbered as their Supplier Payment Voucher (SPV-00001), and
 * reversed - never edited or deleted - when they were a mistake. Payments
 * Made lists them.
 *
 * A payment's money leaves an active payment account that pays suppliers, in
 * the account's currency. When that is not the purchase's currency, what the
 * payment took off the purchase is entered too (section 13), and the rate
 * between the two is kept. Withholding tax kept back from the supplier
 * settles the purchase as well, without leaving the account. Together they
 * never come to more than the purchase still owes: Purchases Lite has no
 * supplier advances or unallocated payments (section 12).
 *
 * Every payment and reversal writes the account's history (AccountLedger) and
 * settles the purchase again (PurchaseSettlement), in one transaction.
 */
class SupplierPaymentService
{
    use RefusesConflicts;

    public function __construct(
        protected SupplierPaymentRepository $supplierPaymentRepository,
        protected PaymentAccountRepository $paymentAccountRepository,
        protected WithholdingTaxRepository $withholdingTaxRepository,
        protected PurchaseService $purchaseService,
        protected PurchaseRules $purchaseRules,
        protected PurchaseSettlement $purchaseSettlement,
        protected AccountLedger $accountLedger,
        protected DocumentNumbers $documentNumbers,
        protected DocumentTemplates $documentTemplates,
        protected WorkspaceAccess $access,
        protected RowLockRepository $rows,
    ) {}

    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->supplierPaymentRepository->paginateFor($workspace, [...$filters, 'location_ids' => $this->access->locations()], $perPage);
    }

    /** Another business's payment answers like one that does not exist. */
    public function find(Workspace $workspace, int $id): SupplierPayment
    {
        return $this->supplierPaymentRepository->findFor($workspace, $id) ?? abort(404, 'Payment not found.');
    }

    /** $data is validated (see RecordSupplierPaymentRequest). */
    public function record(Workspace $workspace, int $purchaseId, User $user, array $data): SupplierPayment
    {
        $purchase = $this->purchaseService->find($workspace, $purchaseId);
        $today    = $this->purchaseService->today($workspace);

        if (! $this->purchaseRules->allows($purchase, 'record_payment')) {
            $this->refuse('Only a recorded purchase with something still to pay takes a payment.');
        }

        $account = $this->paymentAccountRepository->findFor($workspace, $data['payment_account_id']);

        $this->accountLedger->checkMove($account, $data['payment_date'], $today);

        if ($data['payment_date'] < $purchase->document_date) {
            throw ValidationException::withMessages(['payment_date' => "A payment cannot come before its purchase, dated {$purchase->document_date}."]);
        }

        $amount      = Decimal::round((string) $data['amount']);
        $applied     = $this->applied($account, $purchase, $amount, $data);
        $withholding = $this->withholding($workspace, $data);

        $payment = DB::transaction(function () use ($workspace, $user, $data, $purchase, $account, $amount, $applied, $withholding) {
            // What is still owed, read again with the purchase locked: a payment made meanwhile counts (Doc 14 section 22).
            $purchase = $this->rows->lock($purchase);

            if (bccomp(bcadd($applied, $withholding['withholding_amount'], 2), (string) $purchase->balance_due, 2) > 0) {
                throw ValidationException::withMessages([
                    $account->currency === $purchase->currency ? 'amount' : 'applied_amount' => "That comes to more than the {$purchase->currency} {$purchase->balance_due} still owed. Purchases Lite takes no supplier advances.",
                ]);
            }

            $payment = $this->supplierPaymentRepository->create($workspace, [
                'number'             => $this->documentNumbers->next(
                    $workspace,
                    DocumentType::SupplierVoucher,
                    CarbonImmutable::parse($data['payment_date']),
                    fn (string $number) => $this->supplierPaymentRepository->voucherNumberTaken($workspace, $number),
                    $purchase->location_id,
                ),
                'purchase_id'        => $purchase->id,
                'contact_id'         => $purchase->contact_id,
                'payment_account_id' => $account->id,
                'location_id'        => $purchase->location_id,
                'payment_date'       => $data['payment_date'],
                'method'             => $data['method'] ?? null,
                'reference'          => $data['reference'] ?? null,
                'notes'              => $data['notes'] ?? null,
                'amount'             => $amount,
                'applied_amount'     => $applied,
                'exchange_rate'      => bcdiv($amount, $applied, 8),
                'base_amount'        => $this->accountLedger->baseAmount($workspace, $amount, $account->currency, $applied, $purchase->currency, $data['payment_date'], (string) $purchase->exchange_rate),
                ...$withholding,
                'balance_after'      => bcsub(bcsub((string) $purchase->balance_due, $applied, 2), $withholding['withholding_amount'], 2),
                'template_snapshot'  => $this->documentTemplates->snapshot($workspace, DocumentType::SupplierVoucher),
                'created_by'         => $user->id,
            ]);

            $this->accountLedger->recordSupplierPayment($workspace, $payment);
            $this->purchaseSettlement->settle($purchase);

            return $payment;
        });

        // Logged here, by what it is, so LogApiWriteActivity does not add its
        // own vaguer entry.
        ActivityLogService::record('created', "Recorded supplier payment {$payment->number} of {$account->currency} {$amount} for purchase {$purchase->number}", $payment, ['source' => 'api']);

        return $this->supplierPaymentRepository->loadShown($payment);
    }

    /** A payment taken back (Doc 09 section 12): it stays on record, marked reversed, and leaves every balance. */
    public function reverse(Workspace $workspace, int $id, User $user, ?string $reason): SupplierPayment
    {
        $payment = $this->find($workspace, $id);

        if (! $payment->isCompleted()) {
            $this->refuse('This payment was reversed already.');
        }

        $purchase = $this->purchaseService->find($workspace, $payment->purchase_id);

        DB::transaction(function () use ($payment, $user, $reason, $purchase) {
            // Reversed once, however often it is asked at the same moment.
            if (! $this->rows->lock($payment)->isCompleted()) {
                $this->refuse('This payment was reversed already.');
            }

            $this->supplierPaymentRepository->update($payment, [
                'status'          => SupplierPayment::REVERSED,
                'reversed_at'     => now(),
                'reversed_by'     => $user->id,
                'reversal_reason' => $reason,
            ]);

            $this->accountLedger->setStatus($payment, SupplierPayment::REVERSED);
            $this->purchaseSettlement->settle($purchase);
        });

        ActivityLogService::record('updated', "Reversed supplier payment {$payment->number}", $payment, ['source' => 'api']);

        return $this->supplierPaymentRepository->loadShown($payment);
    }

    /** What the log calls it: "supplier payment SPV-00001". */
    public function describe(SupplierPayment $payment): string
    {
        return "supplier payment {$payment->number}";
    }

    /**
     * What a payment takes off the purchase, in the purchase's currency: the
     * amount itself in the same currency, or the amount entered for it.
     */
    private function applied(PaymentAccount $account, Purchase $purchase, string $amount, array $data): string
    {
        if ($account->currency === $purchase->currency) {
            return $amount;
        }

        if (empty($data['applied_amount'])) {
            throw ValidationException::withMessages(['applied_amount' => "Enter what this payment pays off the purchase, in {$purchase->currency}."]);
        }

        return Decimal::round((string) $data['applied_amount']);
    }

    /**
     * The withholding tax kept back, as it is on the day: only while the
     * business uses withholding tax, from a rate that applies to supplier
     * payments and is in effect on the payment's date.
     */
    private function withholding(Workspace $workspace, array $data): array
    {
        if (empty($data['withholding_tax_id'])) {
            return ['withholding_amount' => '0.00'];
        }

        $rate = $this->withholdingTaxRepository->findFor($workspace, $data['withholding_tax_id']);

        if (! $workspace->withholding_tax || ! $rate?->usableOn(WithholdingTax::SUPPLIER_PAYMENTS, $data['payment_date'])) {
            throw ValidationException::withMessages(['withholding_tax_id' => 'Choose an active withholding tax for supplier payments, in effect on the payment date.']);
        }

        return [
            'withholding_tax_id' => $rate->id,
            'withholding_name'   => $rate->name,
            'withholding_code'   => $rate->code,
            'withholding_rate'   => $rate->rate,
            'withholding_amount' => Decimal::round((string) $data['withholding_amount']),
        ];
    }
}
