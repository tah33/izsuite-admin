<?php

namespace App\Services\Api\Expenses;

use App\Enums\DocumentType;
use App\Models\User\Expense;
use App\Models\User\ExpensePayment;
use App\Models\User\PaymentAccount;
use App\Models\User\User;
use App\Models\User\WithholdingTax;
use App\Models\User\Workspace;
use App\Repositories\User\ExpensePaymentRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\WithholdingTaxRepository;
use App\Services\Api\PaymentAccounts\AccountLedger;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Documents\DocumentNumbers;
use App\Services\Documents\DocumentTemplates;
use App\Services\Expenses\ExpenseRules;
use App\Services\Shared\ActivityLogService;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Expense payments (Doc 10 sections 12 and 13): recorded against a recorded
 * expense, numbered as their Expense Payment Voucher (EPV-00001), and
 * reversed - never edited or deleted - when they were a mistake. Payments
 * Made lists them.
 *
 * A payment comes out of an active account that pays suppliers and expenses
 * (Doc 05 section 6), on a day the account can take - not before the expense
 * - and never for more than the expense still owes: Expenses Lite takes no
 * advances. From an account in another currency, it says what it pays off
 * the expense in the expense's currency too. Tax withheld from the payee
 * settles the expense without leaving the account.
 */
class ExpensePaymentService
{
    use RefusesConflicts;

    public function __construct(
        protected ExpensePaymentRepository $expensePaymentRepository,
        protected PaymentAccountRepository $paymentAccountRepository,
        protected WithholdingTaxRepository $withholdingTaxRepository,
        protected ExpenseService $expenseService,
        protected ExpenseRules $expenseRules,
        protected ExpenseSettlement $expenseSettlement,
        protected AccountLedger $accountLedger,
        protected DocumentNumbers $documentNumbers,
        protected DocumentTemplates $documentTemplates,
    ) {}

    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->expensePaymentRepository->paginateFor($workspace, $filters, $perPage);
    }

    /** Another business's payment answers like one that does not exist. */
    public function find(Workspace $workspace, int $id): ExpensePayment
    {
        return $this->expensePaymentRepository->findFor($workspace, $id) ?? abort(404, 'Payment not found.');
    }

    /** $data is validated (see RecordExpensePaymentRequest). */
    public function record(Workspace $workspace, int $expenseId, User $user, array $data): ExpensePayment
    {
        $expense = $this->expenseService->find($workspace, $expenseId);
        $today   = $this->expenseService->today($workspace);

        if (! $this->expenseRules->allows($expense, 'record_payment')) {
            $this->refuse('Only a recorded expense with something still to pay takes a payment.');
        }

        $account = $this->paymentAccountRepository->findFor($workspace, $data['payment_account_id']);

        $this->accountLedger->checkMove($account, $data['payment_date'], $today);

        if ($data['payment_date'] < $expense->document_date) {
            throw ValidationException::withMessages(['payment_date' => "A payment cannot come before its expense, dated {$expense->document_date}."]);
        }

        $amount      = Decimal::round((string) $data['amount']);
        $applied     = $this->applied($account, $expense, $amount, $data);
        $withholding = $this->withholding($workspace, $data);

        if (bccomp(bcadd($applied, $withholding['withholding_amount'], 2), (string) $expense->balance_due, 2) > 0) {
            throw ValidationException::withMessages([
                $account->currency === $expense->currency ? 'amount' : 'applied_amount' => "That comes to more than the {$expense->currency} {$expense->balance_due} still owed. Expenses Lite takes no advances.",
            ]);
        }

        $payment = DB::transaction(function () use ($workspace, $user, $data, $expense, $account, $amount, $applied, $withholding) {
            $payment = $this->expensePaymentRepository->create($workspace, [
                'number'             => $this->documentNumbers->next(
                    $workspace,
                    DocumentType::ExpenseVoucher,
                    CarbonImmutable::parse($data['payment_date']),
                    fn (string $number) => $this->expensePaymentRepository->voucherNumberTaken($workspace, $number),
                ),
                'expense_id'         => $expense->id,
                'contact_id'         => $expense->contact_id,
                'payment_account_id' => $account->id,
                'location_id'        => $expense->location_id,
                'payment_date'       => $data['payment_date'],
                'method'             => $data['method'] ?? null,
                'reference'          => $data['reference'] ?? null,
                'notes'              => $data['notes'] ?? null,
                'amount'             => $amount,
                'applied_amount'     => $applied,
                'exchange_rate'      => bcdiv($amount, $applied, 8),
                'base_amount'        => $this->accountLedger->baseAmount($workspace, $amount, $account->currency, $applied, $expense->currency, $data['payment_date'], (string) $expense->exchange_rate),
                ...$withholding,
                'balance_after'      => bcsub(bcsub((string) $expense->balance_due, $applied, 2), $withholding['withholding_amount'], 2),
                'template_snapshot'  => $this->documentTemplates->snapshot($workspace, DocumentType::ExpenseVoucher),
                'created_by'         => $user->id,
            ]);

            $this->accountLedger->recordExpensePayment($workspace, $payment);
            $this->expenseSettlement->settle($expense);

            return $payment;
        });

        // Logged here, by what it is, so LogApiWriteActivity does not add its
        // own vaguer entry.
        ActivityLogService::record('created', "Recorded expense payment {$payment->number} of {$account->currency} {$amount} for expense {$expense->number}", $payment, ['source' => 'api']);

        return $this->expensePaymentRepository->loadShown($payment);
    }

    /** A payment taken back (Doc 10 section 12): it stays on record, marked reversed, and leaves every balance. */
    public function reverse(Workspace $workspace, int $id, User $user, ?string $reason): ExpensePayment
    {
        $payment = $this->find($workspace, $id);

        if (! $payment->isCompleted()) {
            $this->refuse('This payment was reversed already.');
        }

        $expense = $this->expenseService->find($workspace, $payment->expense_id);

        DB::transaction(function () use ($payment, $user, $reason, $expense) {
            $this->expensePaymentRepository->update($payment, [
                'status'          => ExpensePayment::REVERSED,
                'reversed_at'     => now(),
                'reversed_by'     => $user->id,
                'reversal_reason' => $reason,
            ]);

            $this->accountLedger->setStatus($payment, ExpensePayment::REVERSED);
            $this->expenseSettlement->settle($expense);
        });

        ActivityLogService::record('updated', "Reversed expense payment {$payment->number}", $payment, ['source' => 'api']);

        return $this->expensePaymentRepository->loadShown($payment);
    }

    /** What the log calls it: "expense payment EPV-00001". */
    public function describe(ExpensePayment $payment): string
    {
        return "expense payment {$payment->number}";
    }

    /**
     * What a payment takes off the expense, in the expense's currency: the
     * amount itself in the same currency, or the amount entered for it.
     */
    private function applied(PaymentAccount $account, Expense $expense, string $amount, array $data): string
    {
        if ($account->currency === $expense->currency) {
            return $amount;
        }

        if (empty($data['applied_amount'])) {
            throw ValidationException::withMessages(['applied_amount' => "Enter what this payment pays off the expense, in {$expense->currency}."]);
        }

        return Decimal::round((string) $data['applied_amount']);
    }

    /**
     * The withholding tax kept back, as it is on the day: only while the
     * business uses withholding tax, from a rate that applies to expense
     * payments and is in effect on the payment's date.
     */
    private function withholding(Workspace $workspace, array $data): array
    {
        if (empty($data['withholding_tax_id'])) {
            return ['withholding_amount' => '0.00'];
        }

        $rate = $this->withholdingTaxRepository->findFor($workspace, $data['withholding_tax_id']);

        if (! $workspace->withholding_tax || ! $rate?->usableOn(WithholdingTax::EXPENSE_PAYMENTS, $data['payment_date'])) {
            throw ValidationException::withMessages(['withholding_tax_id' => 'Choose an active withholding tax for expense payments, in effect on the payment date.']);
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
