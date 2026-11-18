<?php

namespace App\Http\Resources\Expenses;

use App\Http\Resources\Concerns\HidesValues;
use App\Services\Shared\AllowedActions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An expense payment's voucher, whole - for its page (Doc 06 section 13): its
 * number, date, the expense it paid, who was paid - a contact, or a name
 * typed in - the account and how, the amounts, what the expense had left just
 * after it, and - once reversed - when, by whom and why. Amounts go out as
 * strings to the cent; `amount` is in the account's currency,
 * `applied_amount` in the expense's. Expects expense, contact, account,
 * creator and reverser loaded.
 */
class ExpensePaymentResource extends JsonResource
{
    use HidesValues;

    /** What a user without Expense Values does not see (Doc 12 section 14). */
    private const VALUES = ['amount', 'applied_amount', 'balance_after'];

    public function toArray(Request $request): array
    {
        return $this->hideValues('expenses', [
            'id'              => $this->id,
            'number'          => $this->number,
            'status'          => $this->status,
            'payment_date'    => $this->payment_date,
            'method'          => $this->method,
            'reference'       => $this->reference,
            'notes'           => $this->notes,
            'payee'           => ['id' => $this->contact_id, 'name' => $this->expense->payeeName()],
            'expense'         => ['id' => $this->expense->id, 'number' => $this->expense->number, 'currency' => $this->expense->currency],
            'account'         => ['id' => $this->account->id, 'name' => $this->account->name, 'currency' => $this->account->currency],
            'amount'          => $this->amount,
            'applied_amount'  => $this->applied_amount,
            'withholding'     => $this->withholding_tax_id || $this->withholding_name ? $this->hideValues('expenses', [
                'name'   => $this->withholding_name,
                'rate'   => $this->withholding_rate,
                'amount' => $this->withholding_amount,
            ], ['amount']) : null,
            'balance_after'   => $this->balance_after,
            'recorded_by'     => $this->creator ? trim("{$this->creator->first_name} {$this->creator->last_name}") : null,
            // The app that made it (Doc 14 section 14.5)
            'made_in'         => $this->whenLoaded('sourceApp', fn () => $this->sourceApp?->name),
            'reversed_at'     => $this->reversed_at?->toIso8601String(),
            'reversed_by'     => $this->reverser ? trim("{$this->reverser->first_name} {$this->reverser->last_name}") : null,
            'reversal_reason' => $this->reversal_reason,
            'actions'         => app(AllowedActions::class)->payment('expenses', $this->isCompleted() ? ['reverse'] : []),
        ], self::VALUES);
    }
}
