<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLanguageRequest;
use App\Http\Requests\Admin\UpdateLanguageRequest;
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

            $this->languageService->create($validated);

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

            if ($problem = $this->languageService->deletionProblem($language)) {
                return back()->with('error', $problem);
            }

            $this->languageService->delete($language);

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
            $language = $this->languageService->find($id);
            abort_unless($language, 404);

            return view('admin.languages.translate', ['language' => $language, ...$this->languageService->translationsFor($language)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function saveTranslations(Request $request, int $id)
    {
        try {
            $language = $this->languageService->find($id);
            abort_unless($language, 404);

            $this->languageService->saveTranslations($language, $request->input('translations', []));

            return redirect()->route('admin.languages.translate', $id)
                ->with('success', __('translation_saved'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
