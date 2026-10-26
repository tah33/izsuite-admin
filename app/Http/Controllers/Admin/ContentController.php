<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderContentItemsRequest;
use App\Http\Requests\Admin\StoreContentItemRequest;
use App\Http\Requests\Admin\UpdateContentItemRequest;
use App\Http\Requests\Admin\UploadContentImageRequest;
use App\Http\Resources\Content\ContentImageResource;
use App\Services\Admin\ContentService;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function __construct(
        private readonly ContentService $contentService,
    ) {}

    /**
     * Show the content management page.
     */
    public function index(Request $request)
    {
        try {
            return view('admin.content.index', $this->contentService->page($request->query('context')));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Save settings-based sections (Hero, CTA, Footer).
     */
    public function update(Request $request)
    {
        try {
            $this->contentService->saveSettings($request->input('content', []), $request->file('images', []));

            return back()->with('success', __('Content saved successfully.'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Upload an image for a content item (AJAX).
     */
    public function uploadImage(UploadContentImageRequest $request)
    {
        try {
            $storedImage = $this->contentService->storeImage($request->file('image'));

            return response()->json([
                'success' => true,
                'data'    => new ContentImageResource($storedImage),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Add a new content item to a section (AJAX).
     */
    public function storeItem(StoreContentItemRequest $request)
    {
        try {
            $this->contentService->addItem($request->validated('section'), $request->validated('data'));

            return response()->json(['success' => true]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Update a content item (AJAX).
     */
    public function updateItem(UpdateContentItemRequest $request, int $id)
    {
        try {
            $this->contentService->updateItem($id, $request->validated('data'));

            return response()->json(['success' => true]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Delete a content item (AJAX).
     */
    public function destroyItem(int $id)
    {
        try {
            $this->contentService->deleteItem($id);

            return response()->json(['success' => true]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Reorder content items (AJAX).
     */
    public function reorderItems(ReorderContentItemsRequest $request)
    {
        try {
            $this->contentService->reorder($request->validated('ids'));

            return response()->json(['success' => true]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
