<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Services\Admin\AdminLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            $credentials = $request->validated();

            if (! $this->adminLoginService->credentialsMatchAnAdmin($credentials['email'], $credentials['password'])) {
                return back()->withErrors([
                    'email' => 'The provided credentials do not match our records.',
                ])->onlyInput('email');
            }

            if (Auth::attempt([
                'email'    => $credentials['email'],
                'password' => $credentials['password'],
            ], $request->boolean('remember'))) {
                $request->session()->regenerate();

                $this->adminLoginService->recordLogin($request->user());

                return redirect()->intended(route('admin.overview'));
            }

            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function logout(Request $request)
    {
        try {
            $this->adminLoginService->recordLogout();

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('home');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
