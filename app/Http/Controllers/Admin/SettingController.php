<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingRequest;
use App\Mail\TestMail;
use App\Services\Shared\ActivityLogService;
use App\Services\Admin\SettingService;
use App\Services\Support\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

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
            $this->settingService->ensureDefaults();

            $grouped = $this->settingService->getGrouped();

            return view('admin.settings.index', compact('grouped'));

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
            $group    = $request->input('_group');
            $settings = $request->validated()['settings'] ?? $request->input('settings', []);

            $this->syncBooleanSettings($request, $settings, $group);

            if ($group === 'ai') {
                $this->ensureActiveProviderRequirements($settings);
            }

            if ($group === 'mail') {
                $this->handleMailSettings($settings);
            }

            if ($group === 'branding') {
                $this->handleBrandingUploads($request, $settings);
            }

            $this->settingService->bulkUpdate($settings);

            ActivityLogService::record('updated', "Updated site settings group: $group");

            return back()->with('success', ucfirst($group).' settings saved successfully.')
                ->with('active_tab', $group);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Handle site branding file uploads (logo and favicon).
     */
    private function handleBrandingUploads(Request $request, array &$settings): void
    {
        $imageService  = app(ImageService::class);
        $brandingFiles = ['site_logo', 'site_favicon'];

        foreach ($brandingFiles as $key) {
            if ($request->hasFile("settings.{$key}")) {
                $oldPath        = setting($key);

                $path           = $imageService->storePublic(
                    $request->file("settings.{$key}"),
                    'site',
                    $oldPath
                );

                $settings[$key] = $path;
            } else {
                // Keep the old file if no new one is uploaded
                unset($settings[$key]);
            }
        }
    }

    /**
     * Sync checkbox/boolean values.
     */
    private function syncBooleanSettings(Request $request, array &$settings, ?string $group = null): void
    {
        $keys = [
            'ai'     => ['ai_openai_enabled', 'ai_gemini_enabled'],
            'mail'   => ['smtp_enabled'],
            'social' => ['google_login_enabled', 'linkedin_login_enabled'],
        ];

        if ($group && isset($keys[$group])) {
            foreach ($keys[$group] as $key) {
                $settings[$key] = $request->boolean("settings.$key") ? '1' : '0';
            }
        } elseif (! $group) {
            // Fallback for global update if needed
            foreach ($keys as $groupKeys) {
                foreach ($groupKeys as $key) {
                    if ($request->has("settings.$key")) {
                        $settings[$key] = $request->boolean("settings.$key") ? '1' : '0';
                    }
                }
            }
        }
    }

    /**
     * Send a test email using the saved mail settings.
     */
    public function testMail(Request $request)
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        try {
            Mail::to($validated['test_email'])->send(new TestMail);

            ActivityLogService::record('updated', "Sent a test email to {$validated['test_email']}");

            $mailer  = config('mail.default');
            $message = "Test email sent to {$validated['test_email']} using the \"{$mailer}\" mailer.";

            if ($mailer !== 'smtp') {
                $message .= ' SMTP is currently disabled, so the message did not actually leave the server.';
            }

            return back()->with('success', $message)->with('active_tab', 'mail');

        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not send the test email: '.$e->getMessage())
                ->with('active_tab', 'mail');
        }
    }

    /**
     * Validate the mail group and keep secrets that were submitted blank.
     */
    private function handleMailSettings(array &$settings): void
    {
        // The password input is rendered empty on purpose, so an empty submit
        // means "keep the stored one" rather than "clear it".
        if (blank($settings['smtp_password'] ?? null)) {
            unset($settings['smtp_password']);
        }

        if (($settings['smtp_enabled'] ?? setting('smtp_enabled')) !== '1') {
            return;
        }

        $required = [
            'smtp_host'         => 'SMTP host is required when SMTP is enabled.',
            'smtp_port'         => 'SMTP port is required when SMTP is enabled.',
            'smtp_from_address' => 'From address is required when SMTP is enabled.',
        ];

        $errors = [];

        foreach ($required as $key => $message) {
            if (blank($settings[$key] ?? setting($key))) {
                $errors["settings.$key"] = $message;
            }
        }

        if (filled($settings['smtp_username'] ?? setting('smtp_username'))
            && blank($settings['smtp_password'] ?? setting('smtp_password'))) {
            $errors['settings.smtp_password'] = 'SMTP password is required when a username is set.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Ensure the active AI provider has all required settings.
     */
    private function ensureActiveProviderRequirements(array $settings): void
    {
        $activeProvider = $settings['ai_active_provider'] ?? setting('ai_active_provider', 'openai');

        if ($activeProvider === 'openai') {
            $this->validateOpenAi($settings);
        }

        if ($activeProvider === 'gemini') {
            $this->validateGemini($settings);
        }
    }

    /**
     * Validate OpenAI specific requirements.
     */
    private function validateOpenAi(array $settings): void
    {
        // Only require API key if OpenAI is actually enabled
        if (($settings['ai_openai_enabled'] ?? setting('ai_openai_enabled')) === '1') {
            if (blank($settings['ai_openai_api_key'] ?? setting('ai_openai_api_key'))) {
                throw ValidationException::withMessages(['settings.ai_openai_api_key' => 'OpenAI API key is required when OpenAI is enabled.']);
            }

            if (blank($settings['ai_openai_model'] ?? setting('ai_openai_model'))) {
                throw ValidationException::withMessages(['settings.ai_openai_model' => 'OpenAI model is required when OpenAI is enabled.']);
            }
        }
    }

    /**
     * Validate Gemini specific requirements.
     */
    private function validateGemini(array $settings): void
    {
        // Only require API key if Gemini is actually enabled
        if (($settings['ai_gemini_enabled'] ?? setting('ai_gemini_enabled')) === '1') {
            if (blank($settings['ai_gemini_api_key'] ?? setting('ai_gemini_api_key'))) {
                throw ValidationException::withMessages(['settings.ai_gemini_api_key' => 'Gemini API key is required when Gemini is enabled.']);
            }

            if (blank($settings['ai_gemini_model'] ?? setting('ai_gemini_model'))) {
                throw ValidationException::withMessages(['settings.ai_gemini_model' => 'Gemini model is required when Gemini is enabled.']);
            }
        }
    }
}
