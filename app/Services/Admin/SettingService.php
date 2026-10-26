<?php

namespace App\Services\Admin;

use App\Mail\TestMail;
use App\Models\Admin\Setting;
use App\Repositories\Admin\CurrencyRepository;
use App\Repositories\Admin\LanguageRepository;
use App\Repositories\Admin\SettingRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Support\ImageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SettingService
{
    private const DEFAULTS = [
        'ai_active_provider'     => ['value' => 'openai', 'group' => 'ai'],
        'ai_openai_enabled'      => ['value' => '1', 'group' => 'ai'],
        'ai_openai_api_key'      => ['value' => '', 'group' => 'ai'],
        'ai_openai_model'        => ['value' => 'gpt-4.1-mini', 'group' => 'ai'],
        'ai_gemini_enabled'      => ['value' => '0', 'group' => 'ai'],
        'ai_gemini_api_key'      => ['value' => '', 'group' => 'ai'],
        'ai_gemini_model'        => ['value' => 'gemini-2.0-flash', 'group' => 'ai'],
        'ai_temperature'         => ['value' => '0.7', 'group' => 'ai'],
        'ai_max_tokens'          => ['value' => '1000', 'group' => 'ai'],

        // Mail / SMTP
        'smtp_enabled'           => ['value' => '0', 'group' => 'mail'],
        'smtp_host'              => ['value' => '', 'group' => 'mail'],
        'smtp_port'              => ['value' => '587', 'group' => 'mail'],
        'smtp_encryption'        => ['value' => 'tls', 'group' => 'mail'],
        'smtp_username'          => ['value' => '', 'group' => 'mail'],
        'smtp_password'          => ['value' => '', 'group' => 'mail'],
        'smtp_from_address'      => ['value' => '', 'group' => 'mail'],
        'smtp_from_name'         => ['value' => '', 'group' => 'mail'],

        // Social Login
        'google_login_enabled'   => ['value' => '0', 'group' => 'social'],
        'google_client_id'       => ['value' => '', 'group' => 'social'],
        'google_client_secret'   => ['value' => '', 'group' => 'social'],
        'linkedin_login_enabled' => ['value' => '0', 'group' => 'social'],
        'linkedin_client_id'     => ['value' => '', 'group' => 'social'],
        'linkedin_client_secret' => ['value' => '', 'group' => 'social'],

        // Branding
        'site_logo'              => ['value' => '', 'group' => 'branding'],
        'site_favicon'           => ['value' => '', 'group' => 'branding'],
        'primary_color'          => ['value' => '#1769E8', 'group' => 'branding'],
        'footer_text'            => ['value' => '', 'group' => 'branding'],
    ];

    public function __construct(
        protected SettingRepository $settingRepo,
        protected CurrencyRepository $currencyRepo,
        protected LanguageRepository $languageRepo,
        protected ImageService $imageService,
    ) {}

    /**
     * What the settings page shows: every setting by group, and the currencies
     * and languages the defaults can be set to.
     */
    public function page(): array
    {
        $this->ensureDefaults();

        return [
            'grouped'         => $this->getGrouped(),
            'currencyOptions' => $this->currencyRepo->activeByName(),
            'languageOptions' => $this->languageRepo->activeByName(),
        ];
    }

    public function ensureDefaults(): void
    {
        foreach (self::DEFAULTS as $key => $config) {
            $existing = $this->settingRepo->getSetting($key);
            if ($existing === null) {
                $this->settingRepo->set($key, $config['value'], $config['group']);
            } elseif ($existing->group !== $config['group']) {
                // Fix group if it's different on live
                $this->settingRepo->update($existing, ['group' => $config['group']]);
                Setting::clearCache();
            }
        }
    }

    /**
     * Get all settings grouped, with fields ordered the way they are declared
     * in self::DEFAULTS (keys we do not declare stay alphabetical, at the end).
     */
    public function getGrouped(): array
    {
        $grouped = $this->settingRepo->getGrouped();

        $declaredOrder = array_flip(array_keys(self::DEFAULTS));

        foreach ($grouped as $group => $settings) {
            usort($settings, function ($a, $b) use ($declaredOrder) {
                $posA = $declaredOrder[$a['key']] ?? PHP_INT_MAX;
                $posB = $declaredOrder[$b['key']] ?? PHP_INT_MAX;

                return $posA === $posB
                    ? strcmp($a['key'], $b['key'])
                    : $posA <=> $posB;
            });

            $grouped[$group] = $settings;
        }

        return $grouped;
    }

    /**
     * Get settings by group.
     */
    public function getByGroup(string $group): Collection
    {
        return $this->settingRepo->getByGroup($group);
    }

    /**
     * Get a setting value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settingRepo->get($key, $default);
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, mixed $value, string $group = 'general'): Setting
    {
        return $this->settingRepo->set($key, $value, $group);
    }

    /**
     * Bulk update settings.
     */
    public function bulkUpdate(array $data): void
    {
        $this->settingRepo->bulkUpdate($data);
    }

    /**
     * Saves one tab of the settings page: $settings as UpdateSettingRequest
     * gives them, with the logo and favicon uploaded among $files. The AI
     * and mail tabs are checked first.
     */
    public function saveGroup(?string $group, array $settings, array $files): void
    {
        if ($group === 'ai') {
            $this->ensureActiveProviderRequirements($settings);
        }

        if ($group === 'mail') {
            $this->handleMailSettings($settings);
        }

        if ($group === 'branding') {
            $this->handleBrandingUploads($files, $settings);
        }

        $this->bulkUpdate($settings);

        ActivityLogService::record('updated', "Updated site settings group: $group");
    }

    /**
     * Sends a test email to $address using the saved mail settings: whether
     * it went, and what to tell the admin either way.
     *
     * @return array{sent: bool, message: string}
     */
    public function sendTestMail(string $address): array
    {
        try {
            Mail::to($address)->send(new TestMail);

            ActivityLogService::record('updated', "Sent a test email to {$address}");

            $mailer  = config('mail.default');
            $message = "Test email sent to {$address} using the \"{$mailer}\" mailer.";

            if ($mailer !== 'smtp') {
                $message .= ' SMTP is currently disabled, so the message did not actually leave the server.';
            }

            return ['sent' => true, 'message' => $message];

        } catch (\Throwable $e) {
            report($e);

            return ['sent' => false, 'message' => 'Could not send the test email: '.$e->getMessage()];
        }
    }

    /**
     * Handle site branding file uploads (logo and favicon).
     */
    private function handleBrandingUploads(array $files, array &$settings): void
    {
        $brandingFiles = ['site_logo', 'site_favicon'];

        foreach ($brandingFiles as $key) {
            $file = $files[$key] ?? null;

            if ($file instanceof UploadedFile && $file->isValid()) {
                $oldPath        = $this->settingRepo->get($key);

                $path           = $this->imageService->storePublic(
                    $file,
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
     * Validate the mail group and keep secrets that were submitted blank.
     */
    private function handleMailSettings(array &$settings): void
    {
        // The password input is rendered empty on purpose, so an empty submit
        // means "keep the stored one" rather than "clear it".
        if (blank($settings['smtp_password'] ?? null)) {
            unset($settings['smtp_password']);
        }

        if (($settings['smtp_enabled'] ?? $this->settingRepo->get('smtp_enabled')) !== '1') {
            return;
        }

        $required = [
            'smtp_host'         => 'SMTP host is required when SMTP is enabled.',
            'smtp_port'         => 'SMTP port is required when SMTP is enabled.',
            'smtp_from_address' => 'From address is required when SMTP is enabled.',
        ];

        $errors = [];

        foreach ($required as $key => $message) {
            if (blank($settings[$key] ?? $this->settingRepo->get($key))) {
                $errors["settings.$key"] = $message;
            }
        }

        if (filled($settings['smtp_username'] ?? $this->settingRepo->get('smtp_username'))
            && blank($settings['smtp_password'] ?? $this->settingRepo->get('smtp_password'))) {
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
        $activeProvider = $settings['ai_active_provider'] ?? $this->settingRepo->get('ai_active_provider', 'openai');

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
        if (($settings['ai_openai_enabled'] ?? $this->settingRepo->get('ai_openai_enabled')) === '1') {
            if (blank($settings['ai_openai_api_key'] ?? $this->settingRepo->get('ai_openai_api_key'))) {
                throw ValidationException::withMessages(['settings.ai_openai_api_key' => 'OpenAI API key is required when OpenAI is enabled.']);
            }

            if (blank($settings['ai_openai_model'] ?? $this->settingRepo->get('ai_openai_model'))) {
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
        if (($settings['ai_gemini_enabled'] ?? $this->settingRepo->get('ai_gemini_enabled')) === '1') {
            if (blank($settings['ai_gemini_api_key'] ?? $this->settingRepo->get('ai_gemini_api_key'))) {
                throw ValidationException::withMessages(['settings.ai_gemini_api_key' => 'Gemini API key is required when Gemini is enabled.']);
            }

            if (blank($settings['ai_gemini_model'] ?? $this->settingRepo->get('ai_gemini_model'))) {
                throw ValidationException::withMessages(['settings.ai_gemini_model' => 'Gemini model is required when Gemini is enabled.']);
            }
        }
    }
}
