<?php

namespace App\Providers;

use App\Support\Timezone;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $hasSettingsTable = Schema::hasTable('settings');

        $this->setTimezoneConfigs($hasSettingsTable);

        if (! $hasSettingsTable) {
            return;
        }

        $this->setLocaleConfigs();
        $this->setMailConfigs();
        $this->setAiConfigs();
        $this->setSocialConfigs();
    }

    /**
     * Set timezone configurations.
     *
     * Storage stays in UTC. Display timezone can be overridden per-request by middleware.
     */
    private function setTimezoneConfigs(bool $hasSettingsTable): void
    {
        $defaultDisplayTimezone = Timezone::UTC;

        if ($hasSettingsTable) {
            $defaultDisplayTimezone = Timezone::resolve(
                setting('timezone', Timezone::UTC),
                Timezone::UTC
            );
        }

        Config::set('app.timezone', Timezone::UTC);
        Config::set('app.storage_timezone', Timezone::UTC);
        Config::set('app.default_display_timezone', $defaultDisplayTimezone);
        Config::set('app.display_timezone', $defaultDisplayTimezone);
    }

    /**
     * Set localization configurations.
     */
    private function setLocaleConfigs(): void
    {
        $defaultLocale = setting('default_language', config('app.locale'));
        app()->setLocale(session('admin_locale', $defaultLocale));
    }

    /**
     * Set mail/SMTP configurations.
     */
    private function setMailConfigs(): void
    {
        $smtpHost = setting('smtp_host');

        // Admin can keep the credentials on file but fall back to the .env
        // mailer by simply switching the toggle off.
        if (setting('smtp_enabled', '0') !== '1' || blank($smtpHost)) {
            return;
        }

        $port       = (int) setting('smtp_port', 587);
        $encryption = setting('smtp_encryption', 'tls');

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $smtpHost);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.username', setting('smtp_username') ?: null);
        Config::set('mail.mailers.smtp.password', setting('smtp_password') ?: null);
        Config::set('mail.mailers.smtp.encryption', $encryption === 'none' ? null : $encryption);

        // Laravel 12 builds the transport from the DSN scheme and ignores the
        // legacy "encryption" key, so it has to be translated explicitly:
        // ssl => implicit TLS (smtps, usually 465), anything else => STARTTLS.
        Config::set('mail.mailers.smtp.scheme', $encryption === 'ssl' || $port === 465 ? 'smtps' : 'smtp');

        if ($fromAddress = setting('smtp_from_address')) {
            Config::set('mail.from.address', $fromAddress);
        }

        if ($fromName = setting('smtp_from_name')) {
            Config::set('mail.from.name', $fromName);
        }
    }

    /**
     * Set AI provider configurations.
     */
    private function setAiConfigs(): void
    {
        Config::set('services.ai.active_provider', setting('ai_active_provider', config('services.ai.active_provider', 'openai')));
        Config::set('services.ai.temperature', (float) setting('ai_temperature', config('services.ai.temperature', 0.7)));
        Config::set('services.ai.max_tokens', (int) setting('ai_max_tokens', config('services.ai.max_tokens', 1000)));
        Config::set('services.ai.providers.openai_enabled', filter_var(setting('ai_openai_enabled', config('services.ai.providers.openai_enabled', true)), FILTER_VALIDATE_BOOL));
        Config::set('services.ai.providers.gemini_enabled', filter_var(setting('ai_gemini_enabled', config('services.ai.providers.gemini_enabled', false)), FILTER_VALIDATE_BOOL));

        Config::set('services.openai.api_key', setting('ai_openai_api_key', config('services.openai.api_key')));
        Config::set('services.openai.model', setting('ai_openai_model', config('services.openai.model', 'gpt-4.1-mini')));
        Config::set('services.gemini.api_key', setting('ai_gemini_api_key', config('services.gemini.api_key')));
        Config::set('services.gemini.model', setting('ai_gemini_model', config('services.gemini.model', 'gemini-2.0-flash')));
    }

    /**
     * Set social login configurations.
     */
    private function setSocialConfigs(): void
    {
        // Google
        if (setting('google_login_enabled', '0') === '1') {
            Config::set('services.google.client_id', setting('google_client_id', config('services.google.client_id')));
            Config::set('services.google.client_secret', setting('google_client_secret'));
            Config::set('services.google.redirect', setting('google_redirect', config('services.google.redirect')));
        }

        // LinkedIn
        if (setting('linkedin_login_enabled', '0') === '1') {
            Config::set('services.linkedin.client_id', setting('linkedin_client_id', config('services.linkedin.client_id')));
            Config::set('services.linkedin.client_secret', setting('linkedin_client_secret', config('services.linkedin.client_secret')));
            Config::set('services.linkedin.redirect', setting('linkedin_redirect', config('services.linkedin.redirect')));
        }
    }
}
