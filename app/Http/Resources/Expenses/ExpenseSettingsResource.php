<?php

namespace App\Http\Resources\Expenses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Settings > Expenses: what new expenses start with - the default expense tax
 * by id, null for none - and whether recording one checks for duplicates.
 * Expects default_expense_tax_id set (ExpenseSettingService).
 */
class ExpenseSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'payment_terms'   => $this->payment_terms,
            'notes'           => $this->notes,
            'duplicate_check' => $this->duplicate_check,

            'default_expense_tax_id' => $this->default_expense_tax_id,
        ];
    }
}
