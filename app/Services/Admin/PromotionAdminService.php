<?php

namespace App\Services\Admin;

use App\Models\Billing\Promotion;
use App\Models\User\User;
use App\Repositories\Admin\CurrencyRepository;
use App\Repositories\Billing\PackageRepository;
use App\Repositories\Billing\PromotionRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\Countries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Promotions (Doc 15 section 15.17 - Phase C3b): the codes, each
 * with how often it was taken. One never taken can be deleted; one taken is
 * paused or ended instead, as its invoices name it.
 */
class PromotionAdminService
{
    public function __construct(
        protected PromotionRepository $promotions,
        protected PackageRepository $packages,
        protected CurrencyRepository $currencies,
    ) {}

    /** @return array{promotions: LengthAwarePaginator, plans: \Illuminate\Support\Collection} the list, and each plan's name by its key */
    public function index(array $filters, int $perPage = 15): array
    {
        return [
            'promotions' => $this->promotions->page($filters, $perPage),
            'plans'      => $this->packages->allByRank()->pluck('name', 'key'),
        ];
    }

    /** @return array{promotion: ?Promotion, uses: int, plans: \Illuminate\Support\Collection, currencies: \Illuminate\Support\Collection, countries: list<array>} what the form offers */
    public function form(?int $id = null): array
    {
        $promotion = $id ? $this->find($id) : null;

        return [
            'promotion'  => $promotion,
            'uses'       => $promotion ? $this->promotions->uses($promotion) : 0,
            'plans'      => $this->packages->allByRank()->reject(fn ($package) => $package->isFree())->values(),
            'currencies' => $this->currencies->activeByName(),
            'countries'  => Countries::all(),
        ];
    }

    public function create(array $data, User $admin): Promotion
    {
        $promotion = $this->promotions->create([...$this->clean($data), 'created_by' => $admin->id]);

        ActivityLogService::record('created', "Added promotion {$promotion->code}: {$promotion->describe()}", $promotion, null, $admin);

        return $promotion;
    }

    public function update(int $id, array $data, User $admin): Promotion
    {
        $promotion = $this->promotions->update($this->find($id), $this->clean($data));

        ActivityLogService::record('updated', "Changed promotion {$promotion->code}: {$promotion->describe()}, ".$promotion->status, $promotion, null, $admin);

        return $promotion;
    }

    /** Deleted - only while never taken. */
    public function delete(int $id, User $admin): void
    {
        $promotion = $this->find($id);

        if (($used = $this->promotions->uses($promotion)) > 0) {
            throw ValidationException::withMessages(['promotion' => "{$promotion->code} was taken ".trans_choice(':count time|:count times', $used).': pause it or end it instead, as its invoices name it.']);
        }

        $this->promotions->delete($promotion);

        ActivityLogService::record('deleted', "Deleted promotion {$promotion->code}", null, null, $admin);
    }

    private function find(int $id): Promotion
    {
        return $this->promotions->find($id) ?? abort(404);
    }

    /** Lists left empty mean all; a percent has no currency. */
    private function clean(array $data): array
    {
        foreach (['packages', 'cycles', 'countries'] as $list) {
            $data[$list] = ! empty($data[$list]) ? array_values($data[$list]) : null;
        }

        if ($data['discount_type'] === Promotion::PERCENT) {
            $data['currency'] = null;
        }

        return $data;
    }
}
