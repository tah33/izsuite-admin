<?php

namespace App\Http\Resources\Expenses;

use App\Http\Resources\Concerns\HidesValues;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One line of an expense, as its page and edit form show it - its category's name and its tax as they were kept, or null for none. */
class ExpenseLineResource extends JsonResource
{
    use HidesValues;

    /** What a user without Expense Values does not see (Doc 12 section 14). */
    private const VALUES = ['unit_price', 'amount'];

    public function toArray(Request $request): array
    {
        return $this->hideValues('expenses', [
            'id'                  => $this->id,
            'expense_category_id' => $this->expense_category_id,
            'category'            => $this->category_name,
            'description'         => $this->description,
            'quantity'            => $this->quantity,
            'unit_price'          => $this->unit_price,
            'amount'              => $this->amount,
            'tax_rate_id'         => $this->tax_rate_id,
            'tax'                 => $this->tax_name !== null
                ? ['name' => $this->tax_name, 'code' => $this->tax_code, 'rate' => $this->tax_rate, 'treatment' => $this->tax_treatment]
                : null,
        ], self::VALUES);
    }
}
