<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRetentionRequest;
use App\Services\Admin\Privacy\RetentionRules;

/** Admin > Data Retention (Doc 15 section 15.24 - Phase G3): what is kept, for how long, and what removes it. */
class RetentionController extends Controller
{
    public function __construct(
        protected RetentionRules $retention,
    ) {}

    public function index()
    {
        try {
            return view('admin.retention.index', ['rules' => $this->retention->rules(), 'exportDays' => $this->retention->exportDays()]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(UpdateRetentionRequest $request)
    {
        try {
            $this->retention->setExportDays((int) $request->validated('privacy_export_days'), $request->user());

            return $this->adminSuccess($request, 'Saved: exports made from now last that long.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
