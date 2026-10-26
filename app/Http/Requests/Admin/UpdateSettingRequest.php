<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'settings.ai_active_provider'     => ['nullable', 'in:openai,gemini'],
            'settings.ai_openai_api_key'      => ['nullable', 'string'],
            'settings.ai_openai_model'        => ['nullable', 'string', 'max:255'],
            'settings.ai_gemini_api_key'      => ['nullable', 'string'],
            'settings.ai_gemini_model'        => ['nullable', 'string', 'max:255'],
            'settings.ai_temperature'         => ['nullable', 'numeric', 'min:0', 'max:2'],
            'settings.ai_max_tokens'          => ['nullable', 'integer', 'min:1', 'max:200000'],
            'settings.smtp_host'              => ['nullable', 'string', 'max:255'],
            'settings.smtp_port'              => ['nullable', 'integer', 'min:1', 'max:65535'],
            'settings.smtp_encryption'        => ['nullable', 'in:tls,ssl,none'],
            'settings.smtp_username'          => ['nullable', 'string', 'max:255'],
            'settings.smtp_password'          => ['nullable', 'string', 'max:255'],
            'settings.smtp_from_address'      => ['nullable', 'email', 'max:255'],
            'settings.smtp_from_name'         => ['nullable', 'string', 'max:255'],
            'settings.google_client_id'       => ['nullable', 'string', 'max:255'],
            'settings.google_client_secret'   => ['nullable', 'string', 'max:255'],
            'settings.linkedin_client_id'     => ['nullable', 'string', 'max:255'],
            'settings.linkedin_client_secret' => ['nullable', 'string', 'max:255'],
            'settings.site_logo'              => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            'settings.site_favicon'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg,ico', 'max:1024'],
            'settings.primary_color'          => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'settings.footer_text'            => ['nullable', 'string', 'max:1000'],
        ];

        return array_filter($rules, function ($key) {
            return $this->has($key) || $this->hasFile($key);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * The settings to save: what was sent - validated where a rule covers it
     * - with the group's switches as '1' or '0', since an unticked checkbox
     * sends nothing.
     */
    public function settingsToSave(): array
    {
        $group    = $this->input('_group');
        $settings = $this->validated()['settings'] ?? $this->input('settings', []);

        $keys     = [
            'ai'     => ['ai_openai_enabled', 'ai_gemini_enabled'],
            'mail'   => ['smtp_enabled'],
            'social' => ['google_login_enabled', 'linkedin_login_enabled'],
        ];

        if ($group && isset($keys[$group])) {
            foreach ($keys[$group] as $key) {
                $settings[$key] = $this->boolean("settings.$key") ? '1' : '0';
            }
        } elseif (! $group) {
            // Fallback for global update if needed
            foreach ($keys as $groupKeys) {
                foreach ($groupKeys as $key) {
                    if ($this->has("settings.$key")) {
                        $settings[$key] = $this->boolean("settings.$key") ? '1' : '0';
                    }
                }
            }
        }

        return $settings;
    }

    public function attributes(): array
    {
        return [
            'settings.smtp_host'         => 'SMTP host',
            'settings.smtp_port'         => 'SMTP port',
            'settings.smtp_encryption'   => 'SMTP encryption',
            'settings.smtp_username'     => 'SMTP username',
            'settings.smtp_password'     => 'SMTP password',
            'settings.smtp_from_address' => 'from address',
            'settings.smtp_from_name'    => 'from name',
        ];
    }
}
