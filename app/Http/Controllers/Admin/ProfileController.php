<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyAdminProfileRequest;
use App\Http\Requests\Admin\UpdateAdminProfilePasswordRequest;
use App\Http\Requests\Admin\UpdateAdminProfileRequest;
use App\Services\Admin\ProfileService;

class ProfileController extends Controller
{
    public function __construct(
        protected ProfileService $profileService,
    ) {}

    public function show()
    {
        try {
            return view('admin.profile.show', [
                'user' => auth()->user(),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(UpdateAdminProfileRequest $request)
    {
        try {
            $this->profileService->update(auth()->user(), $request->validated());

            return back()->with('success', __('Profile updated successfully.'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function updatePassword(UpdateAdminProfilePasswordRequest $request)
    {
        try {
            $this->profileService->changePassword(auth()->user(), $request->password);

            return back()->with('success', __('Password changed successfully.'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(DestroyAdminProfileRequest $request)
    {
        try {
            $user = $request->user();

            if ($problem = $this->profileService->deletionProblem($user)) {
                return back()->with('error', $problem);
            }

            auth()->logout();
            $this->profileService->delete($user);

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('success', __('Your account has been deleted.'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
