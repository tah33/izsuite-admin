<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLanguageRequest;
use App\Http\Requests\Admin\UpdateLanguageRequest;
use App\Services\Shared\ActivityLogService;
use App\Services\Admin\LanguageService;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function __construct(
        protected LanguageService $languageService,
    ) {}

    public function index(Request $request)
    {
        try {
            $languages = $this->languageService->getPaginated($request->input('search'));

            return view('admin.languages.index', compact('languages'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create()
    {
        try {
            return view('admin.languages.create');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(StoreLanguageRequest $request)
    {
        try {
            $validated               = $request->validated();

            $validated['is_active']  = $request->boolean('is_active');
            $validated['is_default'] = $request->boolean('is_default');

            $language                = $this->languageService->create($validated);

            ActivityLogService::record('created', "Created language \"{$language->name}\"", $language);

            return redirect()->route('admin.languages.index')
                ->with('success', 'Language created.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(int $id)
    {
        try {
            $language = $this->languageService->find($id);
            abort_unless($language, 404);

            return view('admin.languages.edit', compact('language'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(UpdateLanguageRequest $request, int $id)
    {
        try {
            $language                = $this->languageService->find($id);
            abort_unless($language, 404);

            $validated               = $request->validated();

            $validated['is_active']  = $request->boolean('is_active');
            $validated['is_default'] = $request->boolean('is_default');

            $this->languageService->update($language, $validated);

            ActivityLogService::record('updated', "Updated language \"{$language->name}\"", $language);

            return redirect()->route('admin.languages.index')
                ->with('success', 'Language updated.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(int $id)
    {
        try {
            $language = $this->languageService->find($id);
            abort_unless($language, 404);

            if ($language->is_default) {
                return back()->with('error', 'Cannot delete the default language.');
            }

            $name     = $language->name;
            $this->languageService->delete($language);

            ActivityLogService::record('deleted', "Deleted language \"{$name}\"");

            return redirect()->route('admin.languages.index')
                ->with('success', 'Language deleted.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function translate(int $id)
    {
        try {
            $language        = $this->languageService->find($id);
            abort_unless($language, 404);

            // Load English source strings
            $sourceFile      = lang_path('en.json');
            $sourceStrings   = file_exists($sourceFile) ? json_decode(file_get_contents($sourceFile), true) : [];

            // Load existing translations for this locale
            $translationFile = lang_path($language->code.'.json');
            $translations    = file_exists($translationFile) ? json_decode(file_get_contents($translationFile), true) : [];

            return view('admin.languages.translate', compact('language', 'sourceStrings', 'translations'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function saveTranslations(Request $request, int $id)
    {
        try {
            $language     = $this->languageService->find($id);
            abort_unless($language, 404);

            $translations = $request->input('translations', []);

            // Filter out empty values
            $translations = array_filter($translations, fn ($value) => $value !== null && $value !== '');

            // Write to JSON file
            $filePath     = lang_path($language->code.'.json');
            file_put_contents($filePath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            ActivityLogService::record('updated', "Updated translations for \"{$language->name}\"", $language);

            return redirect()->route('admin.languages.translate', $id)
                ->with('success', __('translation_saved'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
