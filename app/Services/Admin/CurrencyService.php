<?php

namespace App\Services\Admin;

use App\Models\Admin\Currency;
use App\Repositories\Admin\CurrencyRepository;
use App\Repositories\Admin\SettingRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CurrencyService
{
    public function __construct(
        protected CurrencyRepository $currencyRepo,
        protected SettingRepository $settingRepo,
    ) {}

    public function getPaginated(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->currencyRepo->getPaginated($search, $perPage);
    }

    public function find(int $id): ?Currency
    {
        return $this->currencyRepo->find($id);
    }

    /** The active currency with code $code, if there is one. */
    public function findActiveByCode(string $code): ?Currency
    {
        return $this->currencyRepo->findActiveByCode($code);
    }

    public function create(array $data): Currency
    {
        // If setting as default, unset other defaults
        if ($data['is_default'] ?? false) {
            $this->currencyRepo->clearDefault();
        }

        $currency = $this->currencyRepo->create($data);

        ActivityLogService::record('created', "Created currency \"{$currency->name}\"", $currency);

        return $currency;
    }

    public function update(Currency $currency, array $data): Currency
    {
        if ($data['is_default'] ?? false) {
            $this->currencyRepo->clearDefault($currency);
        }

        $updated = $this->currencyRepo->update($currency, $data);

        ActivityLogService::record('updated', "Updated currency \"{$currency->name}\"", $currency);

        return $updated;
    }

    public function delete(Currency $currency): bool
    {
        $name    = $currency->name;
        $deleted = $this->currencyRepo->delete($currency);

        ActivityLogService::record('deleted', "Deleted currency \"{$name}\"");

        return $deleted;
    }

    /** Why $currency cannot be deleted, if it cannot. */
    public function deletionProblem(Currency $currency): ?string
    {
        return $currency->is_default ? 'Cannot delete the default currency.' : null;
    }

    /** Saves how amounts are written: the formatting settings. */
    public function saveFormatting(array $data): void
    {
        $this->settingRepo->setMany($data);

        ActivityLogService::record('updated', 'Updated currency formatting settings');
    }
}
