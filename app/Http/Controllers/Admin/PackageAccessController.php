<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePackageAccessRequest;
use App\Services\Admin\PackageAccessService;

/**
 * Which plan includes which app, as one matrix (Doc 01 section 3).
 *
 * A higher plan includes everything a lower one does, so only an app's lowest
 * ticked plan counts: saving ticks every plan above it as well. Saving is
 * refused while a plan would hold an app without what that app needs.
 *
 * Access is not activation. Businesses that already have an app on keep it
 * when it leaves their plan; their apps are brought within the plan when the
 * plan itself changes.
 */
class PackageAccessController extends Controller
{
    public function __construct(
        protected PackageAccessService $packageAccessService,
    ) {}

    public function index()
    {
        try {
            return view('admin.package-access.index', $this->packageAccessService->matrix());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SavePackageAccessRequest $request)
    {
        try {
            $problems = $this->packageAccessService->save(
                (array) $request->validated('access', []),
                $request->user(),
                $request->validated('confirm_password'),
                $request->validated('reason'),
            );

            if ($problems) {
                return $this->adminFailure($request, implode(' ', $problems), 422);
            }

            return $this->adminSuccess($request, 'Package access saved.', route('admin.package-access.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
