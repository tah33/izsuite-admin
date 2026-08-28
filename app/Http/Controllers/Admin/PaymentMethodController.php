<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOfflinePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Models\Billing\PaymentMethod;
use App\Services\Shared\ActivityLogService;
use App\Services\Admin\PaymentMethodService;
use App\Services\Support\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
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
            $validated               = $request->validated();

            $validated['type']       = 'offline';
            $validated['slug']       = Str::slug($validated['name']);
            $validated['is_active']  = $request->boolean('is_active');
            $validated['sort_order'] = $validated['sort_order'] ?? 0;

            if ($request->hasFile('image')) {
                $validated['logo_url'] = $this->imageService->storePublic($request->file('image'), 'payment-methods');
            }
            unset($validated['image']);

            $method                  = $this->methodService->create($validated);

            ActivityLogService::record('created', "Created offline payment method \"{$method->name}\"", $method);

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

            if ($method->type === 'online') {
                return $this->updateOnline($request, $method);
            }

            return $this->updateOffline($request, $method);

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

            if ($method->type === 'online') {
                return back()->with('error', 'Online gateways cannot be deleted — only deactivated.');
            }

            $name   = $method->name;
            $this->imageService->deletePublic($method->logo_url);
            $this->methodService->delete($method);

            ActivityLogService::record('deleted', "Deleted offline payment method \"{$name}\"");

            return redirect()->route('admin.payment-methods.index', ['tab' => 'offline'])
                ->with('success', 'Offline method deleted.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    // ── Private ──

    private function updateOffline(UpdatePaymentMethodRequest $request, PaymentMethod $method)
    {
        $validated               = $request->validated();

        $validated['slug']       = Str::slug($validated['name']);
        $validated['is_active']  = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        if ($request->hasFile('image')) {
            $validated['logo_url'] = $this->imageService->storePublic($request->file('image'), 'payment-methods', $method->logo_url);
        }
        unset($validated['image']);

        $this->methodService->update($method, $validated);

        ActivityLogService::record('updated', "Updated offline payment method \"{$method->name}\"", $method);

        return redirect()->route('admin.payment-methods.index', ['tab' => 'offline'])
            ->with('success', 'Offline method updated.');
    }

    private function updateOnline(UpdatePaymentMethodRequest $request, PaymentMethod $method)
    {
        $validated = $request->validated();

        $data      = [
            'is_active'   => $request->boolean('is_active'),
            'is_sandbox'  => $request->boolean('is_sandbox'),
            'credentials' => $validated['credentials'] ?? [],
        ];

        $this->methodService->update($method, $data);

        ActivityLogService::record('updated', "Updated online gateway \"{$method->name}\"", $method);

        return redirect()->route('admin.payment-methods.index', ['tab' => 'online'])
            ->with('success', "{$method->name} settings saved.");
    }
}
