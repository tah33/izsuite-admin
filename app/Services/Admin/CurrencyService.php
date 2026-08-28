<?php

namespace App\Services\Admin;

use App\Models\Admin\Currency;
use App\Repositories\Admin\CurrencyRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CurrencyService
{
    public function __construct(
        protected CurrencyRepository $currencyRepo,
    ) {}

    public function getPaginated(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->currencyRepo->getPaginated($search, $perPage);
    }

    public function find(int $id): ?Currency
    {
        return $this->currencyRepo->find($id);
    }

    public function create(array $data): Currency
    {
        // If setting as default, unset other defaults
        if ($data['is_default'] ?? false) {
            Currency::where('is_default', true)->update(['is_default' => false]);
        }

        return $this->currencyRepo->create($data);
    }

    public function update(Currency $currency, array $data): Currency
    {
        if ($data['is_default'] ?? false) {
            Currency::where('is_default', true)
                ->where('id', '!=', $currency->id)
                ->update(['is_default' => false]);
        }

        return $this->currencyRepo->update($currency, $data);
    }

    public function delete(Currency $currency): bool
    {
        return $this->currencyRepo->delete($currency);
    }
}
