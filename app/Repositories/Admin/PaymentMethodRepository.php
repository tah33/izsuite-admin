<?php

namespace App\Repositories\Admin;

use App\Models\Billing\PaymentMethod;
use App\QueryFilters\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pipeline\Pipeline;

class PaymentMethodRepository
{
    public function getPaginated(string $type, ?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return app(Pipeline::class)
            ->send(PaymentMethod::where('type', $type))
            ->through([
                new SearchFilter($search, columns: ['name', 'slug', 'description']),
            ])
            ->thenReturn()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    /** The ways to pay businesses are offered: each switched on, offline or a gateway that is built - by their order. */
    public function offered(array $builtGateways): Collection
    {
        return PaymentMethod::active()
            ->where(fn ($query) => $query->where('type', 'offline')->orWhereIn('slug', $builtGateways))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getByType(string $type): Collection
    {
        return PaymentMethod::where('type', $type)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getSupportedOnline(array $supportedGateways): Collection
    {
        return PaymentMethod::online()
            ->active()
            ->whereIn('slug', $supportedGateways)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): ?PaymentMethod
    {
        return PaymentMethod::find($id);
    }

    public function findActiveBySlug(string $slug): ?PaymentMethod
    {
        return PaymentMethod::where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    public function findActiveOnlineBySlug(string $slug): ?PaymentMethod
    {
        return PaymentMethod::online()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    public function findOnlineBySlug(string $slug): ?PaymentMethod
    {
        return PaymentMethod::online()
            ->where('slug', $slug)
            ->first();
    }

    public function create(array $data): PaymentMethod
    {
        return PaymentMethod::create($data);
    }

    public function update(PaymentMethod $method, array $data): PaymentMethod
    {
        $method->update($data);

        return $method->fresh();
    }

    public function delete(PaymentMethod $method): bool
    {
        return $method->delete();
    }
}
