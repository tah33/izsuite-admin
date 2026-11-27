<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommercePaymentLink;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\EcommercePaymentLinkRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A payment link's customer saying they paid (Doc 19 section 19.23 - Phase
 * F6b), on its page and without signing in: while the link is active, the
 * way they paid - one of its methods the store still takes - and their
 * reference, as that method asks for one (required, optional, or not at
 * all). Once: kept on the link, which reads as reported until the business
 * records the payment (EcommerceOrderPayments::record() starts from it) or
 * marks it not received (fail() clears it). The order's payment is Pending
 * from then - unless part is paid already - with their reference, and its
 * timeline says so, as the customer's doing. Nothing is recorded as paid:
 * the business confirms it.
 */
class StorefrontPaymentLinkReport
{
    use RefusesConflicts;

    public function __construct(
        protected StorefrontPaymentLinkPage $page,
        protected EcommercePaymentLinkRepository $links,
        protected EcommerceOrderRepository $orders,
    ) {}

    /** $data as ReportStorePaymentRequest takes it: payment_method_id and reference. The link's page as it is then (StorefrontPaymentLinkPage::show()). */
    public function report(string $code, string $token, array $data): array
    {
        [$storefront, $found] = $this->page->find($code, $token);

        DB::transaction(function () use ($storefront, $found, $data) {
            $order = $this->orders->forConfirming($found->order, true);
            $link  = $this->links->findFor($order, $found->id);
            $owed  = $this->page->owed($order);

            match ($this->page->stateOf($link, $order, $owed)) {
                EcommercePaymentLink::EXPIRED  => $this->refuse('This link has expired: ask us for a new one.'),
                EcommercePaymentLink::REVOKED  => $this->refuse('This link is no longer in use: ask us for a new one.'),
                EcommercePaymentLink::PAID     => $this->refuse("Order {$order->number} is paid: there is nothing more to pay through this link."),
                EcommercePaymentLink::REPORTED => $this->refuse('We have your payment already: we will confirm it and e-mail you.'),
                default                        => null,
            };

            $method = $this->page->offered($storefront, $link)->firstWhere('id', (int) $data['payment_method_id'])
                ?? throw ValidationException::withMessages(['payment_method_id' => 'Choose one of the ways to pay this link offers.']);
            $reference = $method->reference === 'none' ? null : (trim((string) ($data['reference'] ?? '')) ?: null);

            if ($method->reference === 'required' && $reference === null) {
                throw ValidationException::withMessages(['reference' => "Give your payment's reference (its transaction ID)."]);
            }

            $this->links->update($link, [
                'reported_at'        => now(),
                'reported_method'    => ['id' => $method->id, 'name' => $method->name, 'type' => $method->type, 'payment_account_id' => $method->payment_account_id],
                'reported_reference' => $reference,
            ]);

            // Waiting to be confirmed - unless part is paid already - and the reference theirs now.
            $this->orders->update($order, [
                ...(in_array($order->payment_status, [EcommerceOrder::UNPAID, EcommerceOrder::PAYMENT_FAILED], true) ? ['payment_status' => EcommerceOrder::PAYMENT_PENDING] : []),
                ...($reference !== null ? ['payment_reference' => $reference] : []),
            ]);

            ActivityLogService::recordCustomer($storefront->workspace, 'updated', "Reported paying {$link->currency} {$link->askedOf($owed)} for online order {$order->number} by {$method->name}"
                .($reference !== null ? ", reference {$reference}," : '').' through its payment link', $order);
        });

        return $this->page->show($code, $token);
    }
}
