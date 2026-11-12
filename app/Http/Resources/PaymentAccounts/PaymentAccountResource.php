<?php

namespace App\Http\Resources\PaymentAccounts;

use App\Services\Shared\EditConflicts;
use App\Http\Resources\Concerns\HidesValues;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAccountResource extends JsonResource
{
    use HidesValues;

    /** What a user without Payment Accounts' View Balances does not see (Doc 12 section 14). */
    private const BALANCES = ['opening_balance', 'balance'];

    /** What a user without View Full Account Details sees masked: "•••• 6819". */
    private const DETAILS = ['account_number', 'swift_code', 'merchant_number'];

    /**
     * Every field, flat, as the table holds it (Doc 05 sections 5 to 9), with
     * its location by name. Balances go out as strings with two decimals
     * ("1500.00"), never as floats: the opening balance as the column holds
     * it, and the recorded balance PaymentAccountService worked out.
     * has_transactions says money has moved through it - which fixes its
     * currency and opening balance. Expects location loaded.
     */
    public function toArray(Request $request): array
    {
        return $this->maskWithout('payment_accounts.details', $this->hideWithout('payment_accounts.balances', [
            'id'                      => $this->id,
            // When it was last changed: sent back as If-Match when it is saved (Doc 14 section 22).
            'version' => EditConflicts::version($this->resource),
            'type'                    => $this->type,
            'name'                    => $this->name,
            'currency'                => $this->currency,
            'location_id'             => $this->location_id,
            'location'                => $this->location?->name,
            'bank_name'               => $this->bank_name,
            'holder_name'             => $this->holder_name,
            'account_number'          => $this->account_number,
            'branch'                  => $this->branch,
            'swift_code'              => $this->swift_code,
            'bank_country'            => $this->bank_country,
            'merchant_name'           => $this->merchant_name,
            'merchant_number'         => $this->merchant_number,
            'settlement_currency'     => $this->settlement_currency,
            'opening_balance'         => $this->opening_balance,
            'opening_balance_date'    => $this->opening_balance_date,
            'balance'                 => $this->balance,
            'has_transactions'        => (bool) $this->has_transactions,
            'payment_instructions'    => $this->payment_instructions,
            'show_on_documents'       => $this->show_on_documents,
            'allow_customer_payments' => $this->allow_customer_payments,
            'allow_supplier_payments' => $this->allow_supplier_payments,
            'default_receiving'       => $this->default_receiving,
            'default_payment'         => $this->default_payment,
            'notes'                   => $this->notes,
            'is_active'               => $this->is_active,

            // Who added it and who last changed it (Doc 14 section 14.5), where it was read with them
            'authors' => $this->whenLoaded('creator', fn () => [
                'added_by'   => $this->creator?->name,
                'added_at'   => $this->created_at?->toIso8601String(),
                'changed_by' => $this->updater?->name,
                'changed_at' => $this->updated_at?->toIso8601String(),
            ]),
        ], self::BALANCES), self::DETAILS);
    }
}
