<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveFaqRequest;
use App\Services\Shared\ActivityLogService;
use App\Services\Admin\FaqService;

class FaqController extends Controller
{
    public function __construct(
        protected FaqService $faqService,
    ) {}

    public function index()
    {
        try {
            $faqs = $this->faqService->getAllPaginated();

            return view('admin.faqs.index', compact('faqs'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create()
    {
        try {
            return view('admin.faqs.create');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveFaqRequest $request)
    {
        try {
            $validated               = $request->validated();

            $validated['sort_order'] = $validated['sort_order'] ?? 0;

            $faq                     = $this->faqService->create($validated);

            ActivityLogService::record('created', "Created FAQ \"{$faq->question}\"", $faq);

            return redirect()->route('admin.faqs.index')
                ->with('success', 'FAQ created successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(int $id)
    {
        try {
            $faq = $this->faqService->find($id);
            abort_unless($faq, 404);

            return view('admin.faqs.edit', compact('faq'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveFaqRequest $request, int $id)
    {
        try {
            $faq                     = $this->faqService->find($id);
            abort_unless($faq, 404);

            $validated               = $request->validated();

            $validated['sort_order'] = $validated['sort_order'] ?? 0;

            $this->faqService->update($faq, $validated);

            ActivityLogService::record('updated', "Updated FAQ \"{$faq->question}\"", $faq);

            return redirect()->route('admin.faqs.index')
                ->with('success', 'FAQ updated successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(int $id)
    {
        try {
            $faq      = $this->faqService->find($id);
            abort_unless($faq, 404);

            $question = $faq->question;
            $this->faqService->delete($faq);

            ActivityLogService::record('deleted', "Deleted FAQ \"{$question}\"");

            return redirect()->route('admin.faqs.index')
                ->with('success', 'FAQ deleted successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
