<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAdminAuditLogRequest;
use App\Services\Admin\AdminAuditLogService;

/**
 * Admin > Audit Log (Doc 15 section 15.26): what admins did and tried,
 * searched, read entry by entry, and exported as CSV by roles allowed to.
 * Nothing here changes an entry.
 */
class AdminAuditLogController extends Controller
{
    public function __construct(
        protected AdminAuditLogService $adminAuditLogService,
    ) {}

    public function index(ListAdminAuditLogRequest $request)
    {
        try {
            return view('admin.audit-log.index', [
                'entries' => $this->adminAuditLogService->page($request->filters(), (int) ($request->validated('per_page') ?? 25)),
                ...$this->adminAuditLogService->filterOptions(),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(int $id)
    {
        try {
            return view('admin.audit-log.show', ['entry' => $this->adminAuditLogService->find($id)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function export(ListAdminAuditLogRequest $request)
    {
        try {
            return $this->adminAuditLogService->export($request->filters());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
