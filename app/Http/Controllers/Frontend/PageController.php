<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Admin\PageService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(
        protected PageService $pageService,
    ) {}

    /**
     * Display a published page by its slug.
     */
    public function show(Request $request, string $slug): View
    {
        try {
            $page = $this->pageService->findPublished($slug);

            if (! $page) {
                abort(404);
            }

            return view('page', [
                'page'     => $page,
                'siteName' => setting('site_name', config('brand.name')),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
