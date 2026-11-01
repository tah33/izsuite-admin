<?php

namespace App\Http\Resources\Expenses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Settings > Expenses: what new expenses start with, and whether recording one checks for duplicates. */
class ExpenseSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'payment_terms'   => $this->payment_terms,
            'notes'           => $this->notes,
            'duplicate_check' => $this->duplicate_check,
        ];
    }
}
