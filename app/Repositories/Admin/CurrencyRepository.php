<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Currency;
use App\QueryFilters\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pipeline\Pipeline;

class CurrencyRepository
{
    /**
     * Public listing: active currencies only, default first. Not paginated -
     * this feeds a dropdown, which needs the whole list.
     *
     * @return Collection<int, Currency>
     */
    public function getActive(): Collection
    {
        return Currency::active()
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
    }

    public function getPaginated(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return app(Pipeline::class)
            ->send(Currency::query())
            ->through([
                new SearchFilter($search, columns: ['name', 'code', 'symbol']),
            ])
            ->thenReturn()
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    public function find(int $id): ?Currency
    {
        return Currency::find($id);
    }

    public function create(array $data): Currency
    {
        return Currency::create($data);
    }

    public function update(Currency $currency, array $data): Currency
    {
        $currency->update($data);

        return $currency->fresh();
    }

    public function delete(Currency $currency): bool
    {
        return $currency->delete();
    }
}
