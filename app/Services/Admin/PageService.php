<?php

namespace App\Services\Admin;

use App\Models\Frontend\Page;
use App\Repositories\Admin\PageRepository;
use App\Services\Shared\ActivityLogService;
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

    /** A published page, for the public site. */
    public function findPublished(string $slug): ?Page
    {
        return $this->pageRepository->findBySlug($slug);
    }

    public function find(int $id): ?Page
    {
        return $this->pageRepository->find($id);
    }

    public function create(array $data): Page
    {
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['slug']       = $data['slug'] ?: Str::slug($data['title']);
        $data['created_by'] = auth()->id();

        $page = $this->pageRepository->create($data);

        ActivityLogService::record('created', "Created page \"{$page->title}\"", $page);

        return $page;
    }

    public function update(Page $page, array $data): Page
    {
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['slug']       = $data['slug'] ?: Str::slug($data['title']);

        $updated = $this->pageRepository->update($page, $data);

        ActivityLogService::record('updated', "Updated page \"{$page->title}\"", $page);

        return $updated;
    }

    public function delete(Page $page): void
    {
        $title = $page->title;
        $this->pageRepository->delete($page);

        ActivityLogService::record('deleted', "Deleted page \"{$title}\"");
    }
}
