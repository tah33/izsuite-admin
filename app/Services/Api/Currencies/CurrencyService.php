<?php

namespace App\Services\Api\Currencies;

use App\Models\Admin\Currency;
use App\Repositories\Admin\CurrencyRepository;
use Illuminate\Database\Eloquent\Collection;

class CurrencyService
{
    public function __construct(
        protected CurrencyRepository $currencyRepository,
    ) {}

    /**
     * Public list of the currencies an admin has left active.
     *
     * @return Collection<int, Currency>
     */
    public function list(): Collection
    {
        return $this->currencyRepository->getActive();
    }
}
