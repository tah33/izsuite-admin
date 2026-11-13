<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddPackagePriceRequest;
use App\Http\Requests\Admin\ApplyPackagePriceRequest;
use App\Http\Requests\Admin\UpdatePackageRequest;
use App\Services\Admin\PackageService;
use Illuminate\Http\Request;

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
            return view('admin.packages.edit', $this->packageService->editPage($id));

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

    /** A new price for the plan, from a day (Doc 15 Phase C3a). */
    public function addPrice(AddPackagePriceRequest $request, int $id)
    {
        try {
            $data = $request->validated();

            return $this->adminSuccess($request, $this->packageService->addPrice($id, $request->user(), $data['currency'], $data['cycle'], (string) $data['amount'], $data['starts_on']));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** A price not started yet, taken away. */
    public function removePrice(Request $request, int $id, int $priceId)
    {
        try {
            return $this->adminSuccess($request, $this->packageService->removePrice($id, $priceId, $request->user()));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The plan's price applied to its subscribers, from a day well ahead - with the password and a reason. */
    public function applyPrice(ApplyPackagePriceRequest $request, int $id)
    {
        try {
            $data = $request->validated();

            return $this->adminSuccess($request, $this->packageService->applyPrice($id, $request->user(), $data['currency'], $data['cycle'], $data['from'], $data['confirm_password'] ?? null, $data['reason'] ?? null));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
