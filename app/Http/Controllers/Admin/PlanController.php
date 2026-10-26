<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePlanRequest;
use App\Services\Admin\PlanService;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function __construct(
        protected PlanService $planService,
    ) {}

    /**
     * Display plan listing.
     */
    public function index(Request $request)
    {
        try {
            $plans = $this->planService->getPaginated($request->input('search'));

            return view('admin.plans.index', compact('plans'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Show create form.
     */
    public function create()
    {
        try {
            $onlineGateways = $this->planService->supportedOnlineGateways();

            return view('admin.plans.create', compact('onlineGateways'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Store a new plan.
     */
    public function store(SavePlanRequest $request)
    {
        try {
            $validated                = $request->validated();
            $validated['is_active']   = $request->boolean('is_active');
            $validated['is_featured'] = $request->boolean('is_featured');

            $this->planService->create($validated);

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan created successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Show edit form.
     */
    public function edit(int $id)
    {
        try {
            $plan           = $this->planService->find($id);
            $onlineGateways = $this->planService->supportedOnlineGateways();
            abort_unless($plan, 404);

            return view('admin.plans.edit', compact('plan', 'onlineGateways'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Update a plan.
     */
    public function update(SavePlanRequest $request, int $id)
    {
        try {
            $plan                     = $this->planService->find($id);
            abort_unless($plan, 404);

            $validated                = $request->validated();
            $validated['is_active']   = $request->boolean('is_active');
            $validated['is_featured'] = $request->boolean('is_featured');

            $this->planService->update($plan, $validated);

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan updated successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Delete a plan.
     */
    public function destroy(int $id)
    {
        try {
            $plan = $this->planService->find($id);
            abort_unless($plan, 404);

            if ($problem = $this->planService->deletionProblem($plan)) {
                return back()->with('error', $problem);
            }

            $this->planService->delete($plan);

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan deleted successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
