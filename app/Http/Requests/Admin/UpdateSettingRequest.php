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
