<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeSubscriptionPlanRequest;
use App\Http\Requests\Admin\ExtendSubscriptionRequest;
use App\Http\Requests\Admin\ListSubscriptionsRequest;
use App\Http\Requests\Admin\SubscriptionReasonRequest;
use App\Services\Admin\SubscriptionAdminService;
use Illuminate\Http\Request;

/**
 * Admin > Subscriptions (Doc 15 section 15.13): businesses' subscriptions to
 * izSuite's plans, one with its invoices and card tries, and izSuite's
 * changes to it - each its own permission, and each change with a reason.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionAdminService $subscriptions,
    ) {}

    public function index(ListSubscriptionsRequest $request)
    {
        try {
            return view('admin.subscriptions.index', [
                'subscriptions' => $this->subscriptions->page($request->validated()),
                'packages'      => $this->subscriptions->packages(),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(int $id)
    {
        try {
            return view('admin.subscriptions.show', $this->subscriptions->details($id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function changePlan(ChangeSubscriptionPlanRequest $request, int $id)
    {
        try {
            return $this->adminSuccess($request, $this->subscriptions->changePlan($id, $request->user(), (int) $request->validated('package_id'), $request->validated('cycle'), $request->validated('reason')));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function extendTrial(ExtendSubscriptionRequest $request, int $id)
    {
        try {
            return $this->adminSuccess($request, $this->subscriptions->extendTrial($id, $request->user(), (int) $request->validated('days'), $request->validated('reason')));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function extendGrace(ExtendSubscriptionRequest $request, int $id)
    {
        try {
            return $this->adminSuccess($request, $this->subscriptions->extendGrace($id, $request->user(), (int) $request->validated('days'), $request->validated('reason')));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function scheduleCancellation(SubscriptionReasonRequest $request, int $id)
    {
        try {
            return $this->adminSuccess($request, $this->subscriptions->scheduleCancellation($id, $request->user(), $request->validated('reason')));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function cancelScheduledChange(SubscriptionReasonRequest $request, int $id)
    {
        try {
            return $this->adminSuccess($request, $this->subscriptions->cancelScheduledChange($id, $request->user(), $request->validated('reason')));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function resend(Request $request, int $id)
    {
        try {
            return $this->adminSuccess($request, $this->subscriptions->resend($id, $request->user()));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function retry(Request $request, int $id)
    {
        try {
            return $this->adminSuccess($request, $this->subscriptions->retry($id, $request->user()));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
