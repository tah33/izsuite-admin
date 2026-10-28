<?php

namespace App\Repositories\User;

use App\Models\User\WithholdingTax;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/**
 * Withholding tax rates belong to one business (workspace). Every lookup is
 * scoped to it, so another business's rate is simply not found.
 */
class WithholdingTaxRepository
{
    /** All of them, active ones first, then by name: a business has a handful, and the list shows every one. */
    public function getFor(Workspace $workspace): Collection
    {
        return $workspace->withholdingTaxes()->orderByDesc('is_active')->orderBy('name')->orderBy('id')->get();
    }

    public function findFor(Workspace $workspace, int $id): ?WithholdingTax
    {
        return $workspace->withholdingTaxes()->find($id);
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): WithholdingTax
    {
        return $workspace->withholdingTaxes()->create($data);
    }

    public function update(WithholdingTax $rate, array $data): WithholdingTax
    {
        $rate->update($data);

        return $rate;
    }

    public function delete(WithholdingTax $rate): void
    {
        $rate->delete();
    }
}
