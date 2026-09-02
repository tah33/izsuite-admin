<?php

namespace App\Services\Admin;

use App\Models\Admin\Setting;
use App\Repositories\Admin\SettingRepository;
use Illuminate\Database\Eloquent\Collection;

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
        'primary_color'          => ['value' => '#2563EB', 'group' => 'branding'],
        'footer_text'            => ['value' => '', 'group' => 'branding'],
    ];

    public function __construct(
        protected SettingRepository $settingRepo,
    ) {}

    public function ensureDefaults(): void
    {
        foreach (self::DEFAULTS as $key => $config) {
            $existing = $this->settingRepo->getSetting($key);
            if ($existing === null) {
                $this->settingRepo->set($key, $config['value'], $config['group']);
            } elseif ($existing->group !== $config['group']) {
                // Fix group if it's different on live
                $existing->update(['group' => $config['group']]);
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
}
