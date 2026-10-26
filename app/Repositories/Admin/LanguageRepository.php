<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Language;
use App\QueryFilters\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pipeline\Pipeline;

class LanguageRepository
{
    /**
     * Public listing: active languages only, default first. Not paginated -
     * this feeds a dropdown, which needs the whole list.
     *
     * @return Collection<int, Language>
     */
    public function getActive(): Collection
    {
        return Language::active()
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
    }

    /** The active ones by name, for a dropdown. */
    public function activeByName(): Collection
    {
        return Language::active()->orderBy('name')->get();
    }

    public function getPaginated(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return app(Pipeline::class)
            ->send(Language::query())
            ->through([
                new SearchFilter($search, columns: ['name', 'code', 'native_name']),
            ])
            ->thenReturn()
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    public function find(int $id): ?Language
    {
        return Language::find($id);
    }

    /**
     * The code of the active language whose code or name is $text, in any case.
     */
    public function activeCodeFor(string $text): ?string
    {
        return Language::active()
            ->where(fn ($query) => $query->whereRaw('LOWER(code) = ?', [mb_strtolower($text)])->orWhereRaw('LOWER(name) = ?', [mb_strtolower($text)]))
            ->value('code');
    }

    /**
     * The active language with code $code, if there is one.
     */
    public function findActiveByCode(string $code): ?Language
    {
        return Language::where('code', $code)->where('is_active', true)->first();
    }

    /**
     * Leave no language the default - apart from $except, when given.
     */
    public function clearDefault(?Language $except = null): void
    {
        Language::where('is_default', true)
            ->when($except, fn ($query) => $query->where('id', '!=', $except->id))
            ->update(['is_default' => false]);
    }

    public function create(array $data): Language
    {
        return Language::create($data);
    }

    public function update(Language $language, array $data): Language
    {
        $language->update($data);

        return $language->fresh();
    }

    public function delete(Language $language): bool
    {
        return $language->delete();
    }
}
