<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Http\Requests\Auth\AdminTwoFactorChallengeRequest;
use App\Services\Admin\AdminLoginService;
use Illuminate\Http\Request;

/**
 * The admin panel's sign-in (Doc 15 section 15.2): the password, then the
 * authenticator app's code when two-factor is on. An admin without it is
 * signed in and sent to set it up.
 */
class AuthController extends Controller
{
    public function __construct(
        protected AdminLoginService $adminLoginService,
    ) {}

    public function showLogin()
    {
        try {
            if (auth()->check() && auth()->user()->isAdmin()) {
                return redirect()->route('admin.overview');
            }

            return view('auth.login');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function login(AdminLoginRequest $request)
    {
        try {
            $user = $this->adminLoginService->checkPassword($request->validated('email'), $request->validated('password'));

            if ($this->adminLoginService->waitForCode($request->session(), $user)) {
                return redirect()->route('login.two-factor');
            }

            $this->adminLoginService->signIn($request, $user);

            return redirect()->route('admin.two-factor.setup');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The code step. Without a sign-in waiting for it - never begun, or over - back to the start, keeping any message. */
    public function showChallenge(Request $request)
    {
        try {
            if (! $this->adminLoginService->awaitingCode($request->session())) {
                $request->session()->reflash();

                return redirect()->route('login');
            }

            return view('auth.two-factor-challenge');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function challenge(AdminTwoFactorChallengeRequest $request)
    {
        try {
            $user = $this->adminLoginService->awaitingCode($request->session());

            if (! $user) {
                return redirect()->route('login')->withErrors(['email' => AdminLoginService::CODE_EXPIRED]);
            }

            $this->adminLoginService->checkCode($request->session(), $user, $request->validated('code'), $request->validated('recovery_code'));
            $this->adminLoginService->signIn($request, $user);

            return redirect()->intended(route('admin.overview'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function logout(Request $request)
    {
        try {
            $this->adminLoginService->signOut($request->session());

            return redirect()->route('home');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
