<?php

namespace App\Services\Billing;

use App\Models\Billing\SubscriptionCredit;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Billing\SubscriptionCreditRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * izSuite's credit to a business (Doc 15 sections 15.13 and 15.14 - Phase
 * C2b): an admin gives it, with a reason; the business's next invoice in its
 * currency takes it off before tax (SubscriptionBilling::issue), and what is
 * left carries on to the invoices after; an invoice cancelled gives back
 * what it took; an admin can take back what is not used. Kept as a ledger,
 * so the credit in a currency is the sum of its movements.
 */
class SubscriptionCredits
{
    public function __construct(
        private SubscriptionCreditRepository $credits,
    ) {}

    public function balance(Workspace $workspace, string $currency): string
    {
        return $this->credits->balance($workspace, $currency);
    }

    /** @return array<string, string> the business's credit by currency, where it has some */
    public function balances(Workspace $workspace): array
    {
        return $this->credits->balances($workspace);
    }

    /** What of $net the business's credit in $currency covers - locked while an invoice takes it, when $lock. */
    public function available(Workspace $workspace, string $currency, string $net, bool $lock = false): string
    {
        $balance = $this->credits->balance($workspace, $currency, $lock);

        if (bccomp($net, '0', 2) <= 0 || bccomp($balance, '0', 2) <= 0) {
            return '0.00';
        }

        return bccomp($balance, $net, 2) < 0 ? $balance : bcadd($net, '0', 2);
    }

    /** $admin gives the business $amount of credit in $currency, for $reason. */
    public function grant(Workspace $workspace, string $currency, string $amount, string $reason, User $admin): SubscriptionCredit
    {
        return $this->credits->create([
            'workspace_id' => $workspace->id, 'currency' => $currency, 'amount' => bcadd($amount, '0', 2),
            'kind'         => SubscriptionCredit::GRANTED, 'reason' => $reason, 'created_by' => $admin->id,
        ]);
    }

    /** $admin takes back $amount of the business's unused credit in $currency, for $reason - refused, on the amount, beyond what is left. */
    public function remove(Workspace $workspace, string $currency, string $amount, string $reason, User $admin): SubscriptionCredit
    {
        return DB::transaction(function () use ($workspace, $currency, $amount, $reason, $admin) {
            $left = $this->credits->balance($workspace, $currency, lock: true);

            if (bccomp($amount, $left, 2) > 0) {
                throw ValidationException::withMessages(['amount' => "No more than {$currency} {$left} of credit is left to take back."]);
            }

            return $this->credits->create([
                'workspace_id' => $workspace->id, 'currency' => $currency, 'amount' => bcmul($amount, '-1', 2),
                'kind'         => SubscriptionCredit::REMOVED, 'reason' => $reason, 'created_by' => $admin->id,
            ]);
        });
    }

    /** $invoice, just issued, takes its adjustment off the business's credit. */
    public function take(SubscriptionInvoice $invoice): void
    {
        $this->move($invoice, SubscriptionCredit::USED, bcmul((string) $invoice->adjustment, '-1', 2));
    }

    /** $invoice is cancelled: the credit it took goes back to the business. */
    public function giveBack(SubscriptionInvoice $invoice): void
    {
        $this->move($invoice, SubscriptionCredit::RETURNED, (string) $invoice->adjustment);
    }

    private function move(SubscriptionInvoice $invoice, string $kind, string $amount): void
    {
        if (bccomp((string) $invoice->adjustment, '0', 2) <= 0) {
            return;
        }

        $this->credits->create([
            'workspace_id' => $invoice->workspace_id, 'currency' => $invoice->currency, 'amount' => $amount,
            'kind'         => $kind, 'subscription_invoice_id' => $invoice->id,
        ]);
    }
}
