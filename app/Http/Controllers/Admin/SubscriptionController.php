<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionRequest;
use App\Services\Admin\SubscriptionService;
use App\Services\Invoices\InvoicePdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected InvoicePdfService $invoicePdfService,
    ) {}

    public function index(Request $request)
    {
        try {
            $filters = [
                'search'        => $request->get('search'),
                'status'        => $request->get('status'),
                'billing_cycle' => $request->get('billing_cycle'),
                'date_from'     => $request->get('date_from'),
                'date_to'       => $request->get('date_to'),
            ];

            return view('admin.subscriptions.index', $this->subscriptionService->history($filters));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create()
    {
        try {
            return view('admin.subscriptions.create', $this->subscriptionService->createFormData());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(StoreSubscriptionRequest $request): RedirectResponse
    {
        try {
            $this->subscriptionService->subscribe($request->validated());

            return redirect()
                ->route('admin.subscriptions.index')
                ->with('success', 'User subscribed to plan successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function pause(int $id): RedirectResponse
    {
        try {
            $subscription = $this->subscriptionService->find($id);
            abort_unless($subscription, 404);

            if ($problem = $this->subscriptionService->pauseProblem($subscription)) {
                return redirect()
                    ->route('admin.subscriptions.index')
                    ->with('error', $problem);
            }

            $this->subscriptionService->pause($subscription);

            return redirect()
                ->route('admin.subscriptions.index')
                ->with('success', 'Subscription paused successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function resume(int $id): RedirectResponse
    {
        try {
            $subscription = $this->subscriptionService->find($id);
            abort_unless($subscription, 404);

            if ($problem = $this->subscriptionService->resumeProblem($subscription)) {
                return redirect()
                    ->route('admin.subscriptions.index')
                    ->with('error', $problem);
            }

            $this->subscriptionService->resume($subscription);

            return redirect()
                ->route('admin.subscriptions.index')
                ->with('success', 'Subscription resumed successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function downloadInvoice(int $id): Response|RedirectResponse
    {
        try {
            $subscription = $this->subscriptionService->find($id);
            abort_unless($subscription, 404);

            $invoice = $this->subscriptionService->latestInvoice($subscription);

            if (! $invoice) {
                return redirect()
                    ->route('admin.subscriptions.index')
                    ->with('error', 'No invoice found for this subscription.');
            }

            return $this->invoicePdfService->download($invoice);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
