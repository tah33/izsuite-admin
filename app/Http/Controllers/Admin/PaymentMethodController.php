<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOfflinePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Services\Admin\PaymentMethodService;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function __construct(
        protected PaymentMethodService $methodService,
    ) {}

    /**
     * Tabbed index: ?tab=offline | ?tab=online (default: offline)
     */
    public function index(Request $request)
    {
        try {
            $tab            = $request->input('tab', 'offline');
            $offlineMethods = $this->methodService->getByType('offline');
            $onlineMethods  = $this->methodService->getByType('online');

            return view('admin.payment-methods.index', compact('offlineMethods', 'onlineMethods', 'tab'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Create form for offline payment method.
     */
    public function create()
    {
        try {
            return view('admin.payment-methods.create');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Store offline payment method.
     */
    public function store(StoreOfflinePaymentMethodRequest $request)
    {
        try {
            $validated              = $request->validated();
            $validated['is_active'] = $request->boolean('is_active');

            $this->methodService->createOffline($validated);

            return redirect()->route('admin.payment-methods.index', ['tab' => 'offline'])
                ->with('success', 'Offline method created.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Edit form — works for both offline and online.
     */
    public function edit(int $id)
    {
        try {
            $method = $this->methodService->find($id);
            abort_unless($method, 404);

            if ($method->type === 'online') {
                return view('admin.payment-methods.edit-online', compact('method'));
            }

            return view('admin.payment-methods.edit-offline', compact('method'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Update — offline or online.
     */
    public function update(UpdatePaymentMethodRequest $request, int $id)
    {
        try {
            $method = $this->methodService->find($id);
            abort_unless($method, 404);

            $validated              = $request->validated();
            $validated['is_active'] = $request->boolean('is_active');

            if ($method->type === 'online') {
                $validated['is_sandbox'] = $request->boolean('is_sandbox');

                $this->methodService->updateOnline($method, $validated);

                return redirect()->route('admin.payment-methods.index', ['tab' => 'online'])
                    ->with('success', "{$method->name} settings saved.");
            }

            $this->methodService->updateOffline($method, $validated);

            return redirect()->route('admin.payment-methods.index', ['tab' => 'offline'])
                ->with('success', 'Offline method updated.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Delete offline method only.
     */
    public function destroy(int $id)
    {
        try {
            $method = $this->methodService->find($id);
            abort_unless($method, 404);

            if ($problem = $this->methodService->deletionProblem($method)) {
                return back()->with('error', $problem);
            }

            $this->methodService->deleteOffline($method);

            return redirect()->route('admin.payment-methods.index', ['tab' => 'offline'])
                ->with('success', 'Offline method deleted.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
