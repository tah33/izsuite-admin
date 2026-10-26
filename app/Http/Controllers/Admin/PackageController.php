<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePackageRequest;
use App\Services\Admin\PackageService;

/**
 * Billing Starter, Standard and Premium. Their keys and ranks are fixed (the
 * code relies on them); names, descriptions, storage limits and whether they
 * are offered can change. Which apps each includes is Package Access.
 */
class PackageController extends Controller
{
    public function __construct(
        protected PackageService $packageService,
    ) {}

    public function index()
    {
        try {
            $packages = $this->packageService->listWithCounts();

            return view('admin.packages.index', compact('packages'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(int $id)
    {
        try {
            $package = $this->packageService->findOrFail($id);

            return view('admin.packages.edit', compact('package'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(UpdatePackageRequest $request, int $id)
    {
        try {
            $package = $this->packageService->findOrFail($id);

            $this->packageService->update($package, $request->validated());

            return $this->adminSuccess($request, 'Plan updated successfully.', route('admin.packages.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
