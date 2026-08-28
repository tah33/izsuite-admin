<?php

namespace App\Services\Admin;

use App\Models\Frontend\Page;
use App\Repositories\Admin\PageRepository;
use Illuminate\Support\Str;

class PageService
{
    public function __construct(
        protected PageRepository $pageRepository,
    ) {}

    public function getAllPaginated(int $perPage = 15)
    {
        return $this->pageRepository->getAllPaginated($perPage);
    }

    public function find(int $id): ?Page
    {
        return $this->pageRepository->find($id);
    }

    public function create(array $data): Page
    {
        $data['slug']       = $data['slug'] ?: Str::slug($data['title']);
        $data['created_by'] = auth()->id();

        return $this->pageRepository->create($data);
    }

    public function update(Page $page, array $data): Page
    {
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);

        return $this->pageRepository->update($page, $data);
    }

    public function delete(Page $page): void
    {
        $this->pageRepository->delete($page);
    }
}
