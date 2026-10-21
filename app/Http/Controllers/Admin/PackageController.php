<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Billing\Package;
use App\Services\Shared\ActivityLogService;
use Illuminate\Http\Request;

/**
 * Billing Starter, Standard and Premium. Their keys and ranks are fixed (the
 * code relies on them); names, descriptions and whether they are offered can
 * change. Which apps each includes is Package Access.
 */
class PackageController extends Controller
{
    public function index()
    {
        try {
            $packages = Package::withCount(['apps', 'workspaces'])->orderBy('rank')->get();

            return view('admin.packages.index', compact('packages'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(int $id)
    {
        try {
            $package = Package::findOrFail($id);

            return view('admin.packages.edit', compact('package'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $package = Package::findOrFail($id);

            $request->merge(['is_active' => $request->boolean('is_active')]);

            $validated = $request->validate([
                'name'        => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
                'is_active'   => ['boolean'],
            ]);

            $package->update($validated);

            ActivityLogService::record('updated', "Updated plan \"{$package->name}\"", $package);

            return $this->adminSuccess($request, 'Plan updated successfully.', route('admin.packages.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
