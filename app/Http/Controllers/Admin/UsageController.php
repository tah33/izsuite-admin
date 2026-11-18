<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListUsageRequest;
use App\Http\Requests\Admin\UsageHistoryRequest;
use App\Services\Admin\UsageService;

/** Admin > Usage (Doc 15 section 15.18 - Phase D2): each business's usage against its plan, and its history. */
class UsageController extends Controller
{
    public function __construct(
        protected UsageService $usage,
    ) {}

    public function index(ListUsageRequest $request)
    {
        try {
            return view('admin.usage.index', $this->usage->page($request->validated()));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(UsageHistoryRequest $request, int $id)
    {
        try {
            return view('admin.usage.show', $this->usage->details($id, $request->validated()));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
