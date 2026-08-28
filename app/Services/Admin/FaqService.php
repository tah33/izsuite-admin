<?php

namespace App\Services\Admin;

use App\Models\Admin\Faq;
use App\Repositories\Admin\FaqRepository;
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
        $data['created_by'] = auth()->id();

        return $this->faqRepository->create($data);
    }

    public function update(Faq $faq, array $data): Faq
    {
        return $this->faqRepository->update($faq, $data);
    }

    public function delete(Faq $faq): void
    {
        $this->faqRepository->delete($faq);
    }
}
