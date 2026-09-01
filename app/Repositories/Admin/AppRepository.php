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
        $query = Application::query();

        if (! empty($search)) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%"));
        }

        return $query->latest()->paginate($perPage);
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
        $query = Application::query()->where('is_active', true);

        if (! empty($search)) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%"));
        }

        if (! empty($category)) {
            $query->where('category', $category);
        }

        if (! empty($status)) {
            $query->where('status', $status);
        }

        return $query->orderBy('name')->paginate($perPage)->withQueryString();
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
