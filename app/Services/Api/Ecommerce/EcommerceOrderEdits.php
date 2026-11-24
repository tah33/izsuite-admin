<?php

namespace App\Services\Api\Ecommerce;

use App\Enums\SalesDocumentStatus;
use App\Models\User\EcommerceOrder;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;

/**
 * Editing an online order before payment (Doc 19 section 19.21 - Phase F5),
 * under Edit Orders: an order pending, confirmed or held, nothing of it paid
 * - its invoice, once issued, settled by nothing - and nothing of it
 * fulfilled. Each change is in its timeline with what it was before.
 *
 * - customer(): the customer's details as given (Phase F5a) - its contact
 *   stays the order's, changed in Contacts if at all;
 * - retotal(): after any edit, inside its transaction - the totals worked
 *   out again (EcommerceOrderTotals), and a confirmed order's invoice
 *   changed with them (EcommerceOrderInvoicing::refresh()).
 *
 * Its addresses and delivery are EcommerceOrderDeliveryEdit's.
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

    /** Inside an edit's transaction: $order's totals worked out again, and its issued invoice changed with them. */
    public function retotal(Workspace $workspace, User $user, EcommerceOrder $order): EcommerceOrder
    {
        $this->totals->recalculate($order);

        $order = $this->orders->forConfirming($order, false);

        if ($order->invoice && $order->invoice->status === SalesDocumentStatus::Issued) {
            $this->invoicing->refresh($workspace, $user, $order, $order->invoice, $this->contacts->findFor($workspace, $order->invoice->contact_id));
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
