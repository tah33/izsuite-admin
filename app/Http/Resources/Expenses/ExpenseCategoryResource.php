<?php

namespace App\Http\Resources\Expenses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An expense category, as Settings > Expenses and the expense form list it:
 * its name and description, the tax its lines start with, whether it is
 * offered to new expenses, and `in_use` - an expense line holds it, so it is
 * switched off rather than deleted. Expects in_use loaded.
 */
class ExpenseCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'tax_rate_id' => $this->tax_rate_id,
            'is_active'   => $this->is_active,
            'in_use'      => (bool) ($this->in_use ?? false),
        ];
    }
}
