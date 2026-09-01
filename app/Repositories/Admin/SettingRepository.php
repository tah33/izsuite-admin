<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Setting;
use Illuminate\Database\Eloquent\Collection;

class SettingRepository
{
    /**
     * Get all settings.
     */
    public function getAll(): Collection
    {
        return Setting::orderBy('group')->orderBy('key')->get();
    }

    /**
     * Get settings grouped by group name (only admin-visible groups).
     */
    public function getGrouped(): array
    {
        return Setting::whereIn('group', ['general', 'branding', 'currency', 'notifications', 'ai', 'social', 'mail'])
            ->orderBy('key')
            ->get()
            ->groupBy('group')
            ->toArray();
    }

    /**
     * Get settings by group.
     */
    public function getByGroup(string $group): Collection
    {
        return Setting::where('group', $group)->orderBy('key')->get();
    }

    /**
     * Get a single setting value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }

    /**
     * Get the setting model.
     */
    public function getSetting(string $key): ?Setting
    {
        return Setting::where('key', $key)->first();
    }

    /**
     * Set a single setting.
     */
    public function set(string $key, mixed $value, string $group = 'general'): Setting
    {
        return Setting::set($key, $value, $group);
    }

    /**
     * Bulk update settings from a key => value array.
     */
    public function bulkUpdate(array $data): void
    {
        foreach ($data as $key => $value) {
            // Attempt to find existing setting to preserve its group
            $existing = Setting::where('key', $key)->first();

            if ($existing) {
                $group = $existing->group;
            } else {
                // Logic for new settings based on key prefixes
                $group = 'general';
                if (str_starts_with($key, 'ai_')) {
                    $group = 'ai';
                } elseif (str_starts_with($key, 'google_') || str_starts_with($key, 'linkedin_')) {
                    $group = 'social';
                } elseif (str_starts_with($key, 'site_') || in_array($key, ['primary_color', 'footer_text'])) {
                    $group = 'branding';
                } elseif (str_starts_with($key, 'smtp_')) {
                    $group = 'mail';
                }
            }

            Setting::set($key, $value, $group);
        }

        Setting::clearCache();
    }
}
