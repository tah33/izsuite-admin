<?php

namespace App\Services\Api\Ecommerce;

use App\Enums\SalesDocumentStatus;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommercePaymentLink;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\EcommercePaymentLinkRepository;
use App\Repositories\User\EcommercePaymentMethodRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Editing an online order before payment (Doc 19 section 19.21 - Phase F5),
 * under Edit Orders: an order pending, confirmed or held, nothing of it paid
 * - its invoice, once issued, settled by nothing - and nothing of it
 * fulfilled, nor (Phase F6b) a payment reported through its link waiting to
 * be settled. Each change is in its timeline with what it was before.
 *
 * - customer(): the customer's details as given (Phase F5a) - its contact
 *   stays the order's, changed in Contacts if at all;
 * - discount(): a percentage of its products or an amount, with why (Phase
 *   F5b) - "an authorised discount", by whoever may edit orders;
 * - retotal(): after any edit, inside its transaction - the totals worked
 *   out again (EcommerceOrderTotals), and a confirmed order's invoice
 *   changed with them (EcommerceOrderInvoicing::refresh()); a changed total
 *   revokes its payment links still active (Phase F6a);
 * - checkPaymentLimits(): a total changed by an edit, still within what its
 *   payment method takes (19.22).
 *
 * Its addresses and delivery are EcommerceOrderDeliveryEdit's; its products
 * EcommerceOrderProductEdit's.
 */
class EcommerceOrderEdits
{
    use RefusesConflicts;

    public function __construct(
        protected EcommerceOrderService $orderService,
        protected EcommerceOrderRepository $orders,
        protected EcommerceOrderTotals $totals,
        protected EcommerceOrderInvoicing $invoicing,
        protected ContactRepository $contacts,
        protected EcommercePaymentMethodRepository $paymentMethods,
        protected EcommercePaymentLinkRepository $paymentLinks,
    ) {}

    /** $order read again, locked, once it may be edited - else refused, saying why. */
    public function locked(EcommerceOrder $order): EcommerceOrder
    {
        $order = $this->orders->forConfirming($order, true);

        $this->checkEditable($order);

        return $order;
    }

    /** $order - read whole, its invoice too (forConfirming()) - refused unless it may be edited. */
    public function checkEditable(EcommerceOrder $order): void
    {
        $why = match (true) {
            ! in_array($order->status, [EcommerceOrder::PENDING, EcommerceOrder::CONFIRMED, EcommerceOrder::ON_HOLD], true) => 'it is '.str_replace('_', ' ', $order->status),
            ! in_array($order->payment_status, [EcommerceOrder::UNPAID, EcommerceOrder::PAYMENT_PENDING, EcommerceOrder::PAYMENT_FAILED], true),
            $order->invoice?->isSettledAtAll() === true                                      => 'part of it is paid',
            ! in_array($order->fulfilment_status, [EcommerceOrder::UNFULFILLED, EcommerceOrder::NOT_REQUIRED], true) => 'it is being fulfilled',
            default                                                                          => null,
        };

        if ($why !== null) {
            $this->refuse("Order {$order->number} can no longer be edited: {$why}.");
        }

        // Phase F6b: its customer said they paid through its link - settled first, so the report is not revoked by a new total.
        if ($this->paymentLinks->reportedFor($order, false)) {
            $this->refuse("The customer of order {$order->number} reported paying through its payment link: record their payment, or mark it not received, before editing the order.");
        }
    }

    /** Order $id's customer as given, corrected: $data as UpdateEcommerceOrderCustomerRequest takes it. The order, as its page shows it. */
    public function customer(Workspace $workspace, int $id, array $data): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);

        DB::transaction(function () use ($found, $data) {
            $order = $this->locked($found);
            $given = [
                'name'    => trim($data['name']),
                'email'   => trim($data['email']),
                'phone'   => trim((string) ($data['phone'] ?? '')) ?: null,
                'company' => trim((string) ($data['company'] ?? '')) ?: null,
            ];
            $changes = $this->changes($order->customer ?? [], $given, ['name' => 'name', 'email' => 'e-mail', 'phone' => 'phone', 'company' => 'company']);

            if ($changes === []) {
                return;
            }

            $this->orders->update($order, ['customer' => $given, 'customer_email' => mb_strtolower($given['email'])]);

            // Logged here, by what happened, so LogApiWriteActivity does not add its own vaguer entry - what each was, in its changes.
            ActivityLogService::record('updated', 'Changed the customer details of '.$this->orderService->describe($order), $order, ['source' => 'api', 'changes' => $changes]);
        });

        return $this->orderService->page($workspace, $id);
    }

    /**
     * Phase F5b: order $id's discount as $data gives it (UpdateEcommerceOrderDiscountRequest):
     * a percentage of its products, an amount no more than they come to, or
     * none - with why, in its timeline. The order, as its page shows it.
     */
    public function discount(Workspace $workspace, User $user, int $id, array $data): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);

        DB::transaction(function () use ($workspace, $user, $found, $data) {
            $order  = $this->locked($found);
            $type   = $data['type'] ?? null;
            $value  = $type === null ? null : bcadd((string) $data['value'], '0', 2);
            $reason = trim($data['reason']);
            $base   = $this->totals->productsBase($order);

            if ($type === EcommerceOrder::AMOUNT && bccomp($value, $base, 2) > 0) {
                throw ValidationException::withMessages(['value' => "The order's products come to {$order->currency} {$base}: give a discount of that much at most."]);
            }

            $before = ['discount' => $this->discountWords($order), 'total' => "{$order->currency} {$order->grand_total}"];
            $total  = (string) $order->grand_total;
            $same   = $type === $order->discount_type && ($type === null || bccomp($value, (string) $order->discount_value, 4) === 0);

            if ($same && ($type === null || $reason === $order->discount_reason)) {
                return;
            }

            $this->orders->update($order, ['discount_type' => $type, 'discount_value' => $value, 'discount_reason' => $type === null ? null : $reason]);

            $order = $this->retotal($workspace, $user, $order);

            $this->checkPaymentLimits($workspace, $order, $total);

            $now         = $this->discountWords($order);
            $description = match (true) {
                $type === null          => 'Took the discount off '.$this->orderService->describe($order),
                $before['discount'] === null => 'Gave '.$this->orderService->describe($order)." a discount of {$now}",
                default                 => 'Changed the discount of '.$this->orderService->describe($order)." to {$now}",
            };

            // Logged here, by what happened, so LogApiWriteActivity does not add its own vaguer entry.
            ActivityLogService::record('updated', "{$description}: {$reason}", $order, ['source' => 'api', 'changes' => $this->changes($before, ['discount' => $now, 'total' => "{$order->currency} {$order->grand_total}"], ['discount' => 'discount', 'total' => 'total'])]);
        });

        return $this->orderService->page($workspace, $id);
    }

    /** Phase F5b: $order's discount in words - "10%", "USD 3.00" - none for none. */
    public function discountWords(EcommerceOrder $order): ?string
    {
        return match ($order->discount_type) {
            EcommerceOrder::PERCENT => rtrim(rtrim((string) $order->discount_value, '0'), '.').'%',
            EcommerceOrder::AMOUNT  => $order->currency.' '.bcadd((string) $order->discount_value, '0', 2),
            default                 => null,
        };
    }

    /**
     * Inside an edit's transaction, once its totals are worked out again:
     * refused when $order's total - $before the edit - has changed past what
     * its payment method takes, its least or its most (19.22). A method gone
     * since, or one changed since in another way, stops nothing.
     */
    public function checkPaymentLimits(Workspace $workspace, EcommerceOrder $order, string $before): void
    {
        $total  = (string) $order->grand_total;
        $method = $order->ecommerce_payment_method_id ? $this->paymentMethods->find($workspace, $order->ecommerce_payment_method_id) : null;

        if (! $method || bccomp($total, $before, 2) === 0) {
            return;
        }

        $limit = match (true) {
            $method->min_order !== null && bccomp($total, (string) $method->min_order, 2) < 0 => "orders of {$order->currency} {$method->min_order} or more",
            $method->max_order !== null && bccomp($total, (string) $method->max_order, 2) > 0 => "orders of up to {$order->currency} {$method->max_order}",
            default                                                                           => null,
        };

        if ($limit !== null) {
            $this->refuse("{$method->name} takes {$limit}, and the order would come to {$order->currency} {$total}: keep it as it was, or cancel the order for the customer to place it again.");
        }
    }

    /**
     * Inside an edit's transaction: $order's totals worked out again, and its
     * issued invoice changed with them. A total changed revokes its payment
     * links still active (Phase F6a): each asked what the order came to.
     */
    public function retotal(Workspace $workspace, User $user, EcommerceOrder $order): EcommerceOrder
    {
        $before = (string) $order->grand_total;

        $this->totals->recalculate($order);

        $order = $this->orders->forConfirming($order, false);

        if ($order->invoice && $order->invoice->status === SalesDocumentStatus::Issued) {
            $this->invoicing->refresh($workspace, $user, $order, $order->invoice, $this->contacts->findFor($workspace, $order->invoice->contact_id));
        }

        if (bccomp((string) $order->grand_total, $before, 2) !== 0) {
            $this->paymentLinks->revokeActive($order, $user->id, EcommercePaymentLink::CHANGED);
        }

        return $order;
    }

    /**
     * What changed between $before and $after among $labels' keys, as the
     * timeline lists an entry's changes (RecordChangeList: "Billing address:
     * … → …"): each its label - in snake case - and what it was and is,
     * null for nothing.
     *
     * @return list<array{setting: string, from: ?string, to: ?string}>
     */
    public function changes(array $before, array $after, array $labels): array
    {
        $changed = [];

        foreach ($labels as $key => $label) {
            $was = ($before[$key] ?? null) === '' ? null : ($before[$key] ?? null);
            $now = ($after[$key] ?? null) === '' ? null : ($after[$key] ?? null);

            if ((string) $was !== (string) $now) {
                $changed[] = ['setting' => $label, 'from' => $was === null ? null : (string) $was, 'to' => $now === null ? null : (string) $now];
            }
        }

        return $changed;
    }
}
