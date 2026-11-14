<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListPromotionsRequest;
use App\Http\Requests\Admin\SavePromotionRequest;
use App\Services\Admin\PromotionAdminService;
use Illuminate\Http\Request;

/** Admin > Promotions (Doc 15 section 15.17 - Phase C3b): promotion codes for new subscriptions. */
class PromotionController extends Controller
{
    public function __construct(
        protected PromotionAdminService $promotions,
    ) {}

    public function index(ListPromotionsRequest $request)
    {
        try {
            return view('admin.promotions.index', $this->promotions->index($request->validated()));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create()
    {
        try {
            return view('admin.promotions.create', $this->promotions->form());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SavePromotionRequest $request)
    {
        try {
            $promotion = $this->promotions->create($request->validated(), $request->user());

            return $this->adminSuccess($request, "Promotion {$promotion->code} is added.", route('admin.promotions.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(int $id)
    {
        try {
            return view('admin.promotions.edit', $this->promotions->form($id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SavePromotionRequest $request, int $id)
    {
        try {
            $promotion = $this->promotions->update($id, $request->validated(), $request->user());

            return $this->adminSuccess($request, "Promotion {$promotion->code} is saved. Invoices it took something off keep their discount.", route('admin.promotions.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id)
    {
        try {
            $this->promotions->delete($id, $request->user());

            return $this->adminSuccess($request, 'The promotion is deleted.', route('admin.promotions.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
