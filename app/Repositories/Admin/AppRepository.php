<?php

namespace App\Repositories\Admin;

use App\Models\Frontend\Application;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AppRepository
{
    /**
     * Get filtered, paginated apps list.
     */
    public function getPaginated(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Application::query()->with(['category', 'packages:id,key,name,rank']);

        if (! empty($search)) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('family_key', 'like', "%{$search}%")
                ->orWhere('app_key', 'like', "%{$search}%")
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$search}%")));
        }

        // Families together, Lite before Full, in the Apps Center's order.
        return $query->orderBy('sort_order')->orderBy('family_key')
            ->orderByRaw("case when edition = 'lite' then 1 else 2 end")
            ->paginate($perPage)->withQueryString();
    }

    /**
     * Public listing: active apps only, optionally filtered.
     */
    public function getActivePaginated(
        ?string $search = null,
        ?string $category = null,
        ?string $status = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = Application::query()->with('category')->where('is_active', true);

        if (! empty($search)) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$search}%")));
        }

        // By the category's name, as the public list has always taken it.
        if (! empty($category)) {
            $query->whereHas('category', fn ($c) => $c->where('name', $category));
        }

        if (! empty($status)) {
            $query->where('status', $status);
        }

        return $query->orderBy('name')->orderBy('sort_order')->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?Application
    {
        return Application::find($id);
    }

    public function create(array $data): Application
    {
        return Application::create($data);
    }

    public function update(Application $app, array $data): Application
    {
        $app->update($data);

        return $app->fresh();
    }

    public function delete(Application $app): ?bool
    {
        return $app->delete();
    }
}
