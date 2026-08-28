<?php

namespace App\Services\Admin;

use App\Models\Admin\Language;
use App\Repositories\Admin\LanguageRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LanguageService
{
    public function __construct(
        protected LanguageRepository $languageRepo,
    ) {}

    public function getPaginated(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->languageRepo->getPaginated($search, $perPage);
    }

    public function find(int $id): ?Language
    {
        return $this->languageRepo->find($id);
    }

    public function create(array $data): Language
    {
        // If setting as default, unset other defaults
        if ($data['is_default'] ?? false) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }

        return $this->languageRepo->create($data);
    }

    public function update(Language $language, array $data): Language
    {
        if ($data['is_default'] ?? false) {
            Language::where('is_default', true)
                ->where('id', '!=', $language->id)
                ->update(['is_default' => false]);
        }

        return $this->languageRepo->update($language, $data);
    }

    public function delete(Language $language): bool
    {
        return $this->languageRepo->delete($language);
    }
}
