<?php

namespace App\Http\Resources\Expenses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of Payments Made (Doc 10 section 3), or of an expense's payment
 * history on its page: what the row shows, no more. `amount` is in the
 * account's currency, `applied_amount` and `withholding_amount` in the
 * expense's. Expects account loaded - and, for Payments Made, expense (with
 * its payee) and contact.
 */
class ExpensePaymentListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'number'             => $this->number,
            'status'             => $this->status,
            'payment_date'       => $this->payment_date,
            'method'             => $this->method,
            'reference'          => $this->reference,
            'payee'              => $this->whenLoaded('expense', fn () => $this->expense?->payeeName()),
            'expense'            => $this->whenLoaded('expense', fn () => ['id' => $this->expense_id, 'number' => $this->expense?->number]),
            'account'            => ['name' => $this->account?->name, 'currency' => $this->account?->currency],
            'amount'             => $this->amount,
            'applied_amount'     => $this->applied_amount,
            'withholding_amount' => $this->withholding_amount,
        ];
    }
}
