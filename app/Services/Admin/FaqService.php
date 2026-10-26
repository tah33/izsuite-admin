<?php

namespace App\Services\Admin;

use App\Models\Admin\Faq;
use App\Repositories\Admin\FaqRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FaqService
{
    public function __construct(
        protected FaqRepository $faqRepository,
    ) {}

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->faqRepository->getAllPaginated($perPage);
    }

    public function find(int $id): ?Faq
    {
        return $this->faqRepository->find($id);
    }

    public function getPublished(): Collection
    {
        return $this->faqRepository->getPublished();
    }

    public function create(array $data): Faq
    {
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['created_by'] = auth()->id();

        $faq = $this->faqRepository->create($data);

        ActivityLogService::record('created', "Created FAQ \"{$faq->question}\"", $faq);

        return $faq;
    }

    public function update(Faq $faq, array $data): Faq
    {
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $updated = $this->faqRepository->update($faq, $data);

        ActivityLogService::record('updated', "Updated FAQ \"{$faq->question}\"", $faq);

        return $updated;
    }

    public function delete(Faq $faq): void
    {
        $question = $faq->question;
        $this->faqRepository->delete($faq);

        ActivityLogService::record('deleted', "Deleted FAQ \"{$question}\"");
    }
}
