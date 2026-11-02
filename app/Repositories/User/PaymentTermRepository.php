<?php

namespace App\Repositories\User;

use App\Models\User\ExpenseSetting;
use App\Models\User\PaymentTerm;
use App\Models\User\PurchaseSetting;
use App\Models\User\SalesSetting;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PaymentTermRepository
{
    /** Where a business keeps payment terms as days: its contacts and its documents. */
    private const HELD_IN = [
        ['contacts', 'customer_payment_terms'],
        ['contacts', 'supplier_payment_terms'],
        ['sales_documents', 'payment_terms'],
        ['purchases', 'payment_terms'],
        ['expenses', 'payment_terms'],
    ];

    /** The whole list, shortest first. */
    public function getFor(Workspace $workspace): Collection
    {
        return $workspace->paymentTerms()->orderBy('days')->orderBy('id')->get();
    }

    public function findFor(Workspace $workspace, int $id): ?PaymentTerm
    {
        return $workspace->paymentTerms()->find($id);
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): PaymentTerm
    {
        return $workspace->paymentTerms()->create($data);
    }

    public function update(PaymentTerm $term, array $data): PaymentTerm
    {
        $term->update($data);

        return $term;
    }

    public function delete(PaymentTerm $term): void
    {
        $term->delete();
    }

    /**
     * The starter list (PaymentTerm::STARTER), each made unless the business
     * has a term of that name or that many days already. Returns how many
     * were made.
     */
    public function addStarter(Workspace $workspace): int
    {
        return collect(PaymentTerm::STARTER)
            ->filter(function (int $days, string $name) use ($workspace) {
                if ($workspace->paymentTerms()->where(fn ($query) => $query->where('name', $name)->orWhere('days', $days))->exists()) {
                    return false;
                }

                $workspace->paymentTerms()->create(['name' => $name, 'days' => $days]);

                return true;
            })
            ->count();
    }

    /**
     * Every number of days the business's contacts, documents and defaults
     * hold - a default never saved counts at the days it starts with. A term
     * of any of them is in use.
     *
     * @return list<int>
     */
    public function daysInUse(Workspace $workspace): array
    {
        $held = collect(self::HELD_IN)->flatMap(fn (array $column) => DB::table($column[0])
            ->where('workspace_id', $workspace->id)
            ->whereNotNull($column[1])
            ->distinct()
            ->pluck($column[1]));

        $defaults = collect([SalesSetting::class, PurchaseSetting::class, ExpenseSetting::class])
            ->map(fn (string $model) => $model::where('workspace_id', $workspace->id)->value('payment_terms') ?? (new $model)->payment_terms);

        return $held->merge($defaults)->map(fn ($days) => (int) $days)->unique()->values()->all();
    }
}
