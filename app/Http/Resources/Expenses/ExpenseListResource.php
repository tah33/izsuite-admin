<?php

namespace App\Http\Resources\Expenses;

use App\Models\User\ExpenseLine;
use App\Services\Expenses\ExpenseRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the expenses list (Doc 10 section 8): what the list shows, no
 * more. `categories` are its lines' categories, each once - the list shows
 * one by name, or "Multiple Categories" opening to them. Overdue says whether
 * a recorded expense is past its due date with something left to pay;
 * `actions` are what it allows now, for its row's Actions, done on its own
 * page. Amounts go out as strings to the cent. Expects contact, location and
 * lines loaded.
 */
class ExpenseListResource extends JsonResource
{
    public function __construct($resource, private string $today)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $rules = app(ExpenseRules::class);

        return [
            'id'             => $this->id,
            'number'         => $this->number,
            'status'         => $this->status->value,
            'payment_status' => $this->payment_status,
            'overdue'        => $rules->overdue($this->resource, $this->today),
            'document_date'  => $this->document_date,
            'due_date'       => $this->due_date,
            'payee'          => $this->resource->payeeName(),
            'categories'     => $this->lines->map(fn (ExpenseLine $line) => $line->category_name)->filter()->unique()->values(),
            'receipt_number' => $this->receipt_number,
            'location'       => $this->location?->name,
            'currency'       => $this->currency,
            'grand_total'    => $this->grand_total,
            'amount_paid'    => $this->amount_paid,
            'balance_due'    => $this->balance_due,
            'actions'        => $rules->actions($this->resource),
        ];
    }
}
