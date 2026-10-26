<?php

namespace App\Services\Admin;

use App\Models\Admin\Language;
use App\Repositories\Admin\LanguageRepository;
use App\Services\Shared\ActivityLogService;
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

    /** The active language with code $code, if there is one. */
    public function findActiveByCode(string $code): ?Language
    {
        return $this->languageRepo->findActiveByCode($code);
    }

    public function create(array $data): Language
    {
        // If setting as default, unset other defaults
        if ($data['is_default'] ?? false) {
            $this->languageRepo->clearDefault();
        }

        $language = $this->languageRepo->create($data);

        ActivityLogService::record('created', "Created language \"{$language->name}\"", $language);

        return $language;
    }

    public function update(Language $language, array $data): Language
    {
        if ($data['is_default'] ?? false) {
            $this->languageRepo->clearDefault($language);
        }

        $updated = $this->languageRepo->update($language, $data);

        ActivityLogService::record('updated', "Updated language \"{$language->name}\"", $language);

        return $updated;
    }

    public function delete(Language $language): bool
    {
        $name    = $language->name;
        $deleted = $this->languageRepo->delete($language);

        ActivityLogService::record('deleted', "Deleted language \"{$name}\"");

        return $deleted;
    }

    /** Why $language cannot be deleted, if it cannot. */
    public function deletionProblem(Language $language): ?string
    {
        return $language->is_default ? 'Cannot delete the default language.' : null;
    }

    /**
     * What the translate page works from, each read from its file under
     * lang/: the English strings, and $language's translations so far.
     */
    public function translationsFor(Language $language): array
    {
        // Load English source strings
        $sourceFile      = lang_path('en.json');
        $sourceStrings   = file_exists($sourceFile) ? json_decode(file_get_contents($sourceFile), true) : [];

        // Load existing translations for this locale
        $translationFile = lang_path($language->code.'.json');
        $translations    = file_exists($translationFile) ? json_decode(file_get_contents($translationFile), true) : [];

        return compact('sourceStrings', 'translations');
    }

    /** Writes $translations to $language's file under lang/, leaving out the empty ones. */
    public function saveTranslations(Language $language, array $translations): void
    {
        // Filter out empty values
        $translations = array_filter($translations, fn ($value) => $value !== null && $value !== '');

        // Write to JSON file
        $filePath     = lang_path($language->code.'.json');
        file_put_contents($filePath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        ActivityLogService::record('updated', "Updated translations for \"{$language->name}\"", $language);
    }
}
