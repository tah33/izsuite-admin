<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\EcommercePaymentLink;
use App\Models\User\SalesPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An online order on its page in the business (Doc 19 sections 19.18 and
 * 19.19 - Phase F1): its number, storefront, location and when it was
 * placed; its three statuses; its customer as given - a guest or the
 * store's account - and the contact it is for; its addresses; how it is
 * delivered and paid, with the customer's reference; its products - each
 * with where its price came from - and its totals as the store showed them,
 * with its taxes; the customer's note, where the visit came from and the
 * consents given; and its e-mails to the customer. From Phase F2, its
 * invoice once it is confirmed, and when and by whom it was; from Phase F3,
 * what the invoice still owes, its payments, and its payment's failures;
 * from Phase F4, why it is on hold or cancelled, the status a hold goes back
 * to, and - on its page - what may want a look (never its customer's).
 *
 * @property EcommerceOrder $resource
 */
class EcommerceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order   = $this->resource;
        $contact = $order->contact;

        return [
            'id'                 => $order->id,
            'number'             => $order->number,
            'placed_at'          => $order->placed_at?->toIso8601String(),
            'storefront'         => $order->storefront?->only(['id', 'name', 'code']),
            'location'           => $order->location?->name,
            'status'             => $order->status,
            'payment_status'     => $order->payment_status,
            'fulfilment_status'  => $order->fulfilment_status,
            // Phase F4: on hold, the status it goes back to; why it is on hold, or was cancelled; what may want a look.
            'held_status'        => $order->held_status,
            'status_reason'      => $order->status_reason,
            'review'             => $order->reviewNotes ?? [],
            // Phase F2: its invoice, once confirmed - and when, and by whom.
            'invoice'            => $order->invoice ? ['id' => $order->invoice->id, 'number' => $order->invoice->number, 'balance_due' => (string) $order->invoice->balance_due] : null,
            'confirmed_at'       => $order->confirmed_at?->toIso8601String(),
            'confirmed_by'       => $order->confirmed_at ? ($order->confirmer?->name ?? 'A former member') : null,
            // Phase F3: the payments recorded against its invoice - reversed ones too, as Sales keeps them - and its failed ones.
            'payments'           => $order->invoice ? $order->invoice->payments->where('kind', SalesPayment::PAYMENT)->map(fn (SalesPayment $payment) => [
                'id'        => $payment->id,
                'number'    => $payment->number,
                'date'      => $payment->payment_date,
                'amount'    => (string) $payment->amount,
                'currency'  => $payment->account?->currency ?? $order->currency,
                'account'   => $payment->account?->name,
                'reference' => $payment->reference,
                'reversed'  => ! $payment->isCompleted(),
            ])->values() : [],
            'payment_failures'   => $order->payment_failures,
            'payment_failure'    => $order->payment_failure_reason,
            'currency'           => $order->currency,
            'exchange_rate'      => (string) $order->exchange_rate,
            'prices_include_tax' => $order->prices_include_tax,
            // Phase F5b: as the business kept prices - what a discount's amount is taken as.
            'price_tax_mode'     => $order->price_tax_mode,
            'customer'           => $order->customer,
            'account'            => $order->customerAccount !== null,
            'contact'            => $contact ? ['id' => $contact->id, 'number' => $contact->contact_number, 'name' => $contact->name, 'is_active' => (bool) $contact->is_active, 'created' => $order->contact_created] : null,
            'billing_address'    => $order->billing_address,
            'shipping_address'   => $order->shipping_address,
            // Phase F5a: with the method's or point's id, for its edit to start from.
            'delivery'           => ['kind' => $order->delivery, 'shipping_method_id' => $order->shipping_method_id, 'pickup_point_id' => $order->pickup_point_id, ...($order->delivery_snapshot ?? [])],
            'payment'            => [
                'name'      => $order->payment_snapshot['name'] ?? null,
                'type'      => $order->payment_snapshot['type'] ?? null,
                'reference' => $order->payment_reference,
            ],
            'note'               => $order->customer_note,
            'campaign'           => $order->campaign,
            'consents'           => ['terms_accepted_at' => $order->consents['terms']['accepted_at'] ?? null, 'marketing' => (bool) ($order->consents['marketing'] ?? false)],
            'lines'              => $order->lines->where('kind', EcommerceOrderLine::PRODUCT)->map(fn (EcommerceOrderLine $line) => [
                'id'         => $line->id,
                // Phase F5b: its listing and variant, so its edit knows a product on the order already.
                'catalog_product_id'         => $line->catalog_product_id,
                'catalog_product_variant_id' => $line->catalog_product_variant_id,
                'title'      => $line->title,
                'options'    => $line->options ?? [],
                'sku'        => $line->sku,
                'item_id'    => $line->item_id,
                'quantity'   => (string) $line->quantity,
                'unit_price' => (string) ($line->shown_unit_price ?? $line->unit_price),
                'amount'     => (string) ($line->shown_amount ?? $line->amount),
                'tax'        => $line->tax_name ? "{$line->tax_name} ".rtrim(rtrim((string) $line->tax_rate, '0'), '.').'%' : null,
                'price_list' => $line->price_source === 'price_list' ? ($line->priceList?->name ?? 'A price list since removed') : null,
            ])->values(),
            'totals'             => [
                'subtotal'    => (string) $order->subtotal,
                'shipping'    => (string) $order->shipping_total,
                'discount'    => (string) $order->discount_total,
                'tax_total'   => (string) $order->tax_total,
                'grand_total' => (string) $order->grand_total,
                'taxes'       => array_map(fn (array $tax) => ['name' => $tax['name'] ?? null, 'rate' => isset($tax['rate']) ? (string) $tax['rate'] : null, 'amount' => (string) ($tax['tax_amount'] ?? '0')], $order->taxes ?? []),
            ],
            // Phase F6a: its payment links, the newest first - what each asks, the methods it offers, to whom and by whom it
            // was sent, until when, and whether active, expired, revoked (and why) or paid - and (Phase F6b) what its customer
            // reported paying by, kept on a link revoked or paid since too.
            'payment_links'      => $order->paymentLinks->map(fn (EcommercePaymentLink $link) => [
                'id'          => $link->id,
                'amount'      => (string) $link->amount,
                'currency'    => $link->currency,
                'methods'     => array_column($link->payment_methods ?? [], 'name'),
                'sent_to'     => $link->sent_to,
                'sent_by'     => $link->creator?->name ?? 'A former member',
                'sent_at'     => $link->created_at?->toIso8601String(),
                'expires_at'  => $link->expires_at->toIso8601String(),
                'state'       => $link->state(),
                'revoked_why' => $link->revoked_why,
                'reported'    => $link->reported_at ? [
                    'at'        => $link->reported_at->toIso8601String(),
                    'method'    => $link->reported_method['name'] ?? null,
                    'reference' => $link->reported_reference,
                ] : null,
            ])->values(),
            // Phase F5b: the discount given - a percentage of its products, or an amount - and why.
            'discount'           => $order->discount_type ? [
                'type'   => $order->discount_type,
                'value'  => (string) $order->discount_value,
                'reason' => $order->discount_reason,
            ] : null,
            'emails'             => $order->emails->map(fn (EcommerceOrderEmail $email) => [
                'kind'    => $email->kind,
                'to'      => $email->to,
                'status'  => $email->status,
                'sent_at' => $email->sent_at?->toIso8601String(),
                'error'   => $email->status === EcommerceOrderEmail::FAILED ? $email->error : null,
            ])->values(),
        ];
    }
}
