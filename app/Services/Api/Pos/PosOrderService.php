<?php

namespace App\Services\Api\Pos;

use App\Enums\Edition;
use App\Models\User\Contact;
use App\Models\User\PosOrder;
use App\Models\User\PosPaymentMethod;
use App\Models\User\PosRegister;
use App\Models\User\Workspace;
use App\Repositories\User\PosOrderRepository;
use App\Repositories\User\PosSettingRepository;
use App\Repositories\User\SalesSettingRepository;
use App\Services\Api\Sales\CustomerCredit;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Completing a POS sale (Doc 18 sections 18.12 to 18.18 - Phases C2 and
 * C3), in the cashier's own open session (PosCounter), to one of the
 * business's active customers - the walk-in one to start.
 *
 * The cart is priced again as it is now (PosPricing): every line needs a
 * price, and the total must be the one the cashier took the payment for,
 * else the sale is refused, saying what it is now. Its discounts and price
 * overrides are the user's to give, each override with why; beyond
 * Settings' limit, the sale needs Discount Beyond the Limit and a reason
 * (PosDiscounts).
 *
 * Its payments - one or several - are each one of the register's methods it
 * takes now, its reference typed where the method needs one. What was
 * handed over may be more than the amount it pays only where the method
 * gives change (cash), the difference given back. A sale paid in cash at
 * all rounds its total to Settings' step - a Cash rounding line, apart from
 * the discounts. Together they pay exactly that total.
 *
 * With the full Sales app, a sale to a named customer can leave what its
 * payments do not pay on their account (Phase D3a): for whoever may create
 * credit sales, within the customer's credit limit - or over it with a
 * warning, or, for a customer who blocks, overridden with a reason. Its
 * invoice is then due by the customer's payment terms.
 *
 * The customer's own credit - their advances and credit notes with credit
 * left - can pay part of it (Phase D3b), up to what they hold. An exchange's
 * return pays it first (Phase E3, PosExchanges), the difference paid by the
 * payments - or paid back in cash.
 *
 * PosSale records the sale - its invoice, payments and order, which keeps
 * the return policy and footer its receipt ends with (Phase C4). Each is
 * logged once, on its invoice.
 */
class PosOrderService
{
    use RefusesConflicts;

    public function __construct(
        protected PosCounter $counter,
        protected PosPricing $pricing,
        protected PosDiscounts $discounts,
        protected PosSale $sale,
        protected PosOrderRepository $orders,
        protected PosSettingRepository $settings,
        protected WorkspaceAccess $access,
        protected CustomerCredit $customerCredit,
        protected SalesSettingRepository $salesSettings,
        protected PosCustomerCredit $posCredit,
        protected PosExchanges $exchanges,
    ) {}

    /**
     * $data is validated (see CompletePosOrderRequest): the customer, the
     * lines - each an item, its quantity, a note, a price typed over and a
     * discount - the order's discount and why it goes beyond the limit, the
     * order's note, the total the payment was taken for, and the payments,
     * each its method, amount, what was handed over and its reference. The
     * order, completed.
     */
    public function complete(Workspace $workspace, array $data): PosOrder
    {
        $session  = $this->counter->selling($workspace);
        $register = $session->register;
        $today    = $this->counter->today($workspace);
        $rate     = $this->counter->rate($workspace, $session, $today);
        $contact  = $this->counter->customer($workspace, (int) $data['contact_id']);
        $lines    = $this->counter->lines($workspace, $data['lines']);
        $discount = $this->counter->discount($data);
        $priced   = $this->pricing->priced($workspace, $register, $contact, $lines, $rate, $today, $discount);

        foreach ($priced['prices'] as $index => $price) {
            if ($price['price'] === null) {
                throw ValidationException::withMessages(["lines.{$index}.item_id" => "{$lines[$index]['item']->name} has no price: set one on the item, or take it out of the sale."]);
            }
        }

        if (bccomp($priced['totals']['grand_total'], Decimal::round((string) $data['total']), 2) !== 0) {
            $this->refuse("The total is now {$register->currency} {$priced['totals']['grand_total']}: check the cart, and take the payment again.");
        }

        $limits = $this->discounts->check($workspace, $lines, $priced, $discount, true, $data['approvals'] ?? []);
        // Phase I2: allowed by the cashier, or approved at the register - and who did.
        $beyond = $limits['beyond'] ? $this->discounts->allowBeyond($workspace, $data['discount_reason'] ?? null, $limits['limit'], $data['approvals'] ?? []) : null;

        // Paid in cash at all: the total rounds to Settings' step.
        $methods = $this->counter->methods($workspace, $register);
        $rows    = array_values($data['payments']);
        $setting = $this->settings->forWorkspace($workspace);
        $step    = $setting->cash_rounding;

        if ($step !== null && collect($rows)->contains(fn (array $row) => $methods->get((int) $row['method_id'])['method']->gives_change ?? false)) {
            $priced = $this->pricing->priced($workspace, $register, $contact, $lines, $rate, $today, $discount, (string) $step);
        }

        $total     = $priced['totals']['grand_total'];
        $onAccount = (bool) ($data['on_account'] ?? false);
        // Phase D3b: the customer's own credit pays part of it first.
        $creditUse = isset($data['credit']) ? Decimal::round((string) $data['credit']) : '0.00';

        if (bccomp($creditUse, '0', 2) > 0 && isset($data['exchange_return_id'])) {
            throw ValidationException::withMessages(['credit' => "An exchange's sale is paid by its return's credit first: take the rest by the register's methods."]);
        }

        if (bccomp($creditUse, '0', 2) > 0) {
            $this->usableCredit($workspace, $contact, $register->currency, $creditUse, $total);
        }

        // Phase E3: an exchange's return pays first.
        $exchange  = isset($data['exchange_return_id']) ? $this->exchanges->usable($workspace, $session, $contact, (int) $data['exchange_return_id'], bcsub($total, $creditUse, 2)) : null;
        $due       = bcsub(bcsub($total, $creditUse, 2), $exchange['applied'] ?? '0.00', 2);
        $payments  = $this->payments($methods, $register, $rows, $due, $onAccount);
        $left      = array_reduce($payments, fn (string $left, array $payment) => bcsub($left, $payment['amount'], 2), $due);
        $credit    = $onAccount && bccomp($left, '0', 2) > 0 ? $this->credit($workspace, $contact, $left, $rate, $data['credit_reason'] ?? null) : null;
        $user     = $this->access->user();
        $order    = $this->sale->record($workspace, $user, $session, $contact, [
            'lines'           => $lines,
            'priced'          => $priced,
            'payments'        => $payments,
            'discount'        => $discount,
            'discount_reason' => $beyond['reason'] ?? null,
            'discount_approver' => $beyond['approver_id'] ?? null,
            'override_approver' => $limits['override_approver']?->id,
            'note'            => trim((string) ($data['note'] ?? '')) ?: null,
            'rate'            => $rate,
            'today'           => $today,
            // Phase C4: what its receipt ends with, as Settings has it now.
            'receipt_texts'   => ['return_policy' => $setting->return_policy, 'footer' => $setting->receipt_footer],
            // Phase D3a: what is left on the customer's account, due by their terms.
            'on_account'      => $credit['amount'] ?? '0.00',
            'credit_reason'   => $credit['reason'] ?? null,
            'terms'           => $credit['terms'] ?? 0,
            'credit'          => $creditUse,
            'exchange'        => $exchange,
        ]);
        $order = $this->orders->loadShown($order);
        $paid  = implode(', ', array_map(fn (array $payment) => "{$payment['method']->name} {$payment['amount']}".($payment['method']->confirms_later ? ' (pending)' : ''), $payments));

        $paid  = implode(', ', array_filter([$exchange ? "Exchange {$exchange['return']->number} {$exchange['applied']}" : '', bccomp($creditUse, '0', 2) > 0 ? "Customer credit {$creditUse}" : '', $paid, $credit ? "on account {$credit['amount']}" : '']));

        $paid = $exchange && bccomp($exchange['payback'], '0', 2) > 0 ? "{$paid}, {$exchange['cash']['name']} {$exchange['payback']} paid back" : $paid;

        ActivityLogService::record('created', "Sold {$order->number} at the register \"{$register->name}\" ({$register->code}) to {$contact->name}, for {$register->currency} {$total}"
            .($paid === '' ? '' : ": {$paid}").($beyond === null ? '' : " - its discount beyond the limit because {$beyond['reason']}")
            // Phase I2: what a manager approved at the register, by name.
            .(($beyond['approver'] ?? null) ? ", approved by {$beyond['approver']->name}" : '')
            .($limits['override_approver'] ? ", prices typed over approved by {$limits['override_approver']->name}" : ''), $order->invoice, ['source' => 'api']);

        // Phase D3a: over the customer's credit limit - warned, or overridden and logged with why.
        if ($credit['check']['overridden'] ?? false) {
            $this->customerCredit->logOverride($order->invoice, "Sold {$order->number}", $credit['reason']);
        } elseif ($credit['check'] ?? null) {
            $order->creditWarning = $credit['check']['message'];
        }

        return $order;
    }

    /** The customer's own credit as a payment (Phase D3b): a named customer's, no more than they hold, nor than the sale's total. */
    private function usableCredit(Workspace $workspace, Contact $contact, string $currency, string $credit, string $total): void
    {
        if ($contact->id === $this->settings->forWorkspace($workspace)->walk_in_contact_id) {
            throw ValidationException::withMessages(['credit' => 'The walk-in customer holds no credit: choose the customer whose credit it is.']);
        }

        if (bccomp($credit, $total, 2) > 0) {
            throw ValidationException::withMessages(['credit' => "Credit pays up to the sale's total of {$currency} {$total}."]);
        }

        $available = $this->posCredit->available($workspace, $contact, $currency);

        if (bccomp($credit, $available, 2) > 0) {
            throw ValidationException::withMessages(['credit' => "{$contact->name} holds {$currency} {$available} of credit."]);
        }
    }

    /**
     * What a sale leaving $left on its customer's account takes (Phase D3a):
     * the full Sales app, Create Credit Sales, and a named customer - not the
     * walk-in one - within their credit limit, or over it as CustomerCredit
     * allows, with $reason. Its terms: the customer's payment terms, else
     * Settings > Sales's.
     *
     * @return array{amount: string, terms: int, check: ?array, reason: ?string}
     */
    private function credit(Workspace $workspace, Contact $contact, string $left, string $rate, ?string $reason): array
    {
        if (! WorkspaceApps::for($workspace)->has('sales', Edition::Full)) {
            $this->refuse('A sale is left on account with the full Sales app: switch it on in the Apps Center, or take the whole payment.');
        }

        abort_unless($this->access->can('pos.selling.credit'), 403, 'Leaving a sale on account needs Create Credit Sales.');

        if ($contact->id === $this->settings->forWorkspace($workspace)->walk_in_contact_id) {
            throw ValidationException::withMessages(['contact_id' => 'A sale is left on account for a named customer, not the walk-in one.']);
        }

        $check = $this->customerCredit->checkAdding($workspace, $contact, Decimal::round(bcmul($left, $rate, 10)), $reason);

        return [
            'amount' => $left,
            'terms'  => (int) ($contact->customer_payment_terms ?? $this->salesSettings->forWorkspace($workspace)->payment_terms),
            'check'  => $check,
            'reason' => ($check['overridden'] ?? false) ? trim((string) $reason) : null,
        ];
    }

    /**
     * $rows checked against $methods, the register's: each one it takes now,
     * its amount, what was handed over - more only where the method gives
     * change - the change, and its reference where it needs one; together
     * exactly $total.
     *
     * @return list<array{method: PosPaymentMethod, account_id: int, amount: string, tendered: string, change: string, reference: ?string}>
     */
    private function payments(Collection $methods, PosRegister $register, array $rows, string $total, bool $onAccount = false): array
    {
        $paid = '0.00';
        $out  = [];

        foreach ($rows as $index => $row) {
            $taken = $methods->get((int) $row['method_id']);

            if (! $taken) {
                throw ValidationException::withMessages(["payments.{$index}.method_id" => 'Choose one of the payment methods this register takes.']);
            }

            $method    = $taken['method'];
            $amount    = Decimal::round((string) $row['amount']);
            $tendered  = isset($row['tendered']) ? Decimal::round((string) $row['tendered']) : $amount;
            $reference = trim((string) ($row['reference'] ?? '')) ?: null;

            if (bccomp($tendered, $amount, 2) < 0) {
                throw ValidationException::withMessages(["payments.{$index}.tendered" => 'What was handed over is less than the amount it pays.']);
            }

            if (bccomp($tendered, $amount, 2) > 0 && ! $method->gives_change) {
                throw ValidationException::withMessages(["payments.{$index}.tendered" => "{$method->name} gives no change: it takes the amount it pays."]);
            }

            if ($method->needs_reference && $reference === null) {
                throw ValidationException::withMessages(["payments.{$index}.reference" => "Type {$method->name}'s reference."]);
            }

            $paid  = bcadd($paid, $amount, 2);
            $out[] = ['method' => $method, 'account_id' => $taken['account_id'], 'amount' => $amount, 'tendered' => $tendered, 'change' => bcsub($tendered, $amount, 2), 'reference' => $reference];
        }

        $left = bcsub($total, $paid, 2);

        // Phase D3a: what is left may stay on the customer's account.
        if (bccomp($left, '0', 2) > 0 && $onAccount) {
            return $out;
        }

        if (bccomp($left, '0', 2) !== 0) {
            throw ValidationException::withMessages(['payments' => bccomp($left, '0', 2) > 0
                ? "{$register->currency} {$left} is still to pay."
                : "The payments come to more than the total of {$register->currency} {$total}: only cash gives change."]);
        }

        return $out;
    }
}
