<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Repositories\Admin\PageRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(
        protected PageRepository $pageRepository,
    ) {}

    /**
     * Display a published page by its slug.
     */
    public function show(Request $request, string $slug): View
    {
        $page = $this->pageRepository->findBySlug($slug);

        if (! $page) {
            abort(404);
        }

        return view('page', [
            'page'     => $page,
            'siteName' => setting('site_name', 'Resumist'),
        ]);
    }
}
