<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListFeatureFlagsRequest;
use App\Http\Requests\Admin\SaveFeatureFlagRequest;
use App\Http\Requests\Admin\SwitchFeatureFlagRequest;
use App\Services\Admin\FeatureFlagAdminService;
use Illuminate\Http\Request;

/** Admin > Feature Flags (Doc 15 section 15.12 - Phase E1): features on for an audience between days, with an emergency off switch. */
class FeatureFlagController extends Controller
{
    public function __construct(
        protected FeatureFlagAdminService $flags,
    ) {}

    public function index(ListFeatureFlagsRequest $request)
    {
        try {
            return view('admin.feature-flags.index', ['flags' => $this->flags->page($request->validated())]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create()
    {
        try {
            return view('admin.feature-flags.create', $this->flags->form());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveFeatureFlagRequest $request)
    {
        try {
            $flag = $this->flags->create($request->validated(), $request->user());

            return $this->adminSuccess($request, "Feature flag {$flag->key} is added, {$flag->status}.", route('admin.feature-flags.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(int $id)
    {
        try {
            return view('admin.feature-flags.edit', $this->flags->form($id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveFeatureFlagRequest $request, int $id)
    {
        try {
            $flag = $this->flags->update($id, $request->validated(), $request->user());

            return $this->adminSuccess($request, "Feature flag {$flag->key} is saved, {$flag->status}.", route('admin.feature-flags.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function switch(SwitchFeatureFlagRequest $request, int $id)
    {
        try {
            $flag = $this->flags->switch($id, $request->boolean('on'), $request->user());

            return $this->adminSuccess($request, "Feature flag {$flag->key} is {$flag->status} everywhere it reaches, from now.", route('admin.feature-flags.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id)
    {
        try {
            $this->flags->delete($id, $request->user());

            return $this->adminSuccess($request, 'The feature flag is deleted.', route('admin.feature-flags.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
