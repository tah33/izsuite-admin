<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeBusinessStatusRequest;
use App\Http\Requests\Admin\ListBusinessesRequest;
use App\Http\Requests\Admin\NotifyBusinessRequest;
use App\Services\Admin\BusinessService;
use App\Support\BusinessStatus;

/**
 * Admin > Businesses (Doc 15 sections 15.5 and 15.6): the list, a business's
 * page, its status, and a message to it. Moving its plan is the existing
 * review (WorkspaceController).
 */
class BusinessController extends Controller
{
    public function __construct(
        protected BusinessService $businessService,
    ) {}

    public function index(ListBusinessesRequest $request)
    {
        try {
            return view('admin.businesses.index', [
                'businesses' => $this->businessService->page($request->filters(), (int) ($request->validated('per_page') ?? 25)),
                ...$this->businessService->filterOptions(),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(int $id)
    {
        try {
            return view('admin.businesses.show', $this->businessService->details($id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function changeStatus(ChangeBusinessStatusRequest $request, int $id)
    {
        try {
            $change = $this->businessService->changeStatus($id, $request->validated(), $request->user());

            return $this->adminSuccess($request, 'The business is now '.BusinessStatus::label($change->status).'.', route('admin.businesses.show', $id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function notify(NotifyBusinessRequest $request, int $id)
    {
        try {
            $this->businessService->notify($id, $request->validated(), $request->user());

            return $this->adminSuccess($request, 'Message sent.', route('admin.businesses.show', $id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
