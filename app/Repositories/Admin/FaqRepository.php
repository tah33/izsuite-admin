<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Faq;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FaqRepository
{
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return Faq::query()
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->paginate(requested_per_page($perPage));
    }

    public function find(int $id): ?Faq
    {
        return Faq::find($id);
    }

    public function getPublished(): Collection
    {
        return Faq::published()->get();
    }

    public function create(array $data): Faq
    {
        return Faq::create($data);
    }

    public function update(Faq $faq, array $data): Faq
    {
        $faq->update($data);

        return $faq->fresh();
    }

    public function delete(Faq $faq): void
    {
        $faq->delete();
    }
}
