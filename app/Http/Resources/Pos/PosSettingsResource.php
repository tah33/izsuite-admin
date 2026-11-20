<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Settings → Point of Sale (Doc 18 Phase A1), as PosSettingService::show()
 * gives it: the walk-in customer, the receipt's return policy and footer,
 * closing sessions' settings - the variance limit (Phase B2a), whether cash
 * is counted by notes and coins and each currency's (Phase B2b) - the
 * payment methods in their order, and the accounts a method may take money
 * into.
 */
class PosSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $settings = $this->resource['settings'];
        $customer = $settings->walkInContact;

        return [
            'walk_in_customer' => $customer ? ['id' => $customer->id, 'name' => $customer->name] : null,
            'return_policy'    => $settings->return_policy,
            'receipt_footer'   => $settings->receipt_footer,
            'variance_limit'   => $settings->variance_limit === null ? null : (string) $settings->variance_limit,
            // Phase F1: above it, a cash movement waits for a manager's approval.
            'cash_movement_limit' => $settings->cash_movement_limit === null ? null : (string) $settings->cash_movement_limit,
            'counts_denominations' => (bool) $settings->counts_denominations,
            // Phase C3: selling - the discount limit (a percentage) and the cash rounding step.
            'discount_limit'   => $settings->discount_limit === null ? null : (string) $settings->discount_limit,
            'cash_rounding'    => $settings->cash_rounding === null ? null : (string) $settings->cash_rounding,
            // Phase D1: how many hours a held order is kept - null for until it is resumed or cancelled.
            'hold_hours'       => $settings->hold_hours,
            // Phase I1: after how many minutes without use a register locks - null for only by its cashier.
            'lock_minutes'     => $settings->lock_minutes,
            'denominations'    => $this->resource['denominations'],
            'payment_methods'  => PosPaymentMethodResource::collection($this->resource['methods']),
            'accounts'         => $this->resource['accounts']->map(fn ($account) => ['id' => $account->id, 'name' => $account->name, 'currency' => $account->currency, 'type' => $account->type])->values(),
        ];
    }
}
