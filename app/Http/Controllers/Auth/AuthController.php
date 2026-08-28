<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Models\User\User;
use App\Services\Shared\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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

            $user        = User::with('role')->where('email', $credentials['email'])->first();

            if (! $user || ! $user->isAdmin() || ! Hash::check($credentials['password'], $user->password)) {
                return back()->withErrors([
                    'email' => 'The provided credentials do not match our records.',
                ])->onlyInput('email');
            }

            if (Auth::attempt([
                'email'    => $credentials['email'],
                'password' => $credentials['password'],
            ], $request->boolean('remember'))) {
                $request->session()->regenerate();

                $request->user()->forceFill([
                    'last_login_at' => now(),
                ])->save();

                ActivityLogService::record('login', 'Logged into the web dashboard');

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
            ActivityLogService::record('logout', 'Logged out from the admin panel');

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
