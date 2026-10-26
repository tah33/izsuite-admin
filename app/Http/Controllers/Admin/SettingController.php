<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendTestMailRequest;
use App\Http\Requests\Admin\UpdateSettingRequest;
use App\Services\Admin\SettingService;

class SettingController extends Controller
{
    public function __construct(
        protected SettingService $settingService,
    ) {}

    /**
     * Show all settings grouped by tab.
     */
    public function index()
    {
        try {
            return view('admin.settings.index', $this->settingService->page());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Bulk update settings.
     */
    public function update(UpdateSettingRequest $request)
    {
        try {
            $group = $request->input('_group');

            $this->settingService->saveGroup($group, $request->settingsToSave(), $request->file('settings', []));

            return back()->with('success', ucfirst($group).' settings saved successfully.')
                ->with('active_tab', $group);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Send a test email using the saved mail settings.
     */
    public function testMail(SendTestMailRequest $request)
    {
        try {
            ['sent' => $sent, 'message' => $message] = $this->settingService->sendTestMail($request->validated('test_email'));

            return back()->with($sent ? 'success' : 'error', $message)->with('active_tab', 'mail');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
