<?php

namespace App\Services\Admin;

use App\Models\Frontend\Application;
use App\Repositories\Admin\AppRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AppService
{
    public function __construct(
        protected AppRepository $appRepository,
    ) {}

    /**
     * Get filtered, paginated apps list.
     */
    public function getFilteredApps(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->appRepository->getPaginated($search, $perPage);
    }

    /**
     * Find app by ID.
     */
    public function find(int $id): ?Application
    {
        return $this->appRepository->find($id);
    }

    /**
     * Create a new app.
     */
    public function create(array $data): Application
    {
        return $this->appRepository->create($data);
    }

    /**
     * Update an app.
     */
    public function update(Application $app, array $data): Application
    {
        return $this->appRepository->update($app, $data);
    }

    /**
     * Delete an app.
     */
    public function delete(Application $app): ?bool
    {
        return $this->appRepository->delete($app);
    }
}
