<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmAdminTwoFactorRequest;
use App\Http\Requests\Admin\NewAdminRecoveryCodesRequest;
use App\Services\Admin\AdminTwoFactorService;
use Illuminate\Http\Request;

/**
 * An admin's own two-factor sign-in (Doc 15 section 15.2): setting it up -
 * which every admin must, before the rest of the panel opens - and new
 * recovery codes. Recovery codes are shown once, on the page after.
 */
class AdminTwoFactorController extends Controller
{
    public function __construct(
        protected AdminTwoFactorService $adminTwoFactorService,
    ) {}

    public function setup(Request $request)
    {
        try {
            $user = $request->user();

            if ($this->adminTwoFactorService->isOn($user)) {
                return redirect()->route('admin.overview');
            }

            return view('admin.two-factor.setup', $this->adminTwoFactorService->pending($user));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function confirm(ConfirmAdminTwoFactorRequest $request)
    {
        try {
            $codes = $this->adminTwoFactorService->confirm($request->session(), $request->user(), $request->validated('code'));

            return redirect()->route('admin.two-factor.recovery-codes')
                ->with('success', __('Two-factor sign-in is on.'))
                ->with('recovery_codes', $codes);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The recovery codes just made, this once. A second look finds none. */
    public function recoveryCodes(Request $request)
    {
        try {
            $codes = $request->session()->get('recovery_codes');

            if (! is_array($codes)) {
                return redirect()->route('admin.profile');
            }

            return view('admin.two-factor.recovery-codes', ['codes' => $codes]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function newRecoveryCodes(NewAdminRecoveryCodesRequest $request)
    {
        try {
            $codes = $this->adminTwoFactorService->newRecoveryCodes($request->user(), $request->validated('password'));

            return redirect()->route('admin.two-factor.recovery-codes')
                ->with('success', __('New recovery codes made. The old ones no longer work.'))
                ->with('recovery_codes', $codes);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
