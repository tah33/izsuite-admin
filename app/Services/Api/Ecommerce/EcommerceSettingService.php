<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceSetting;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceSettingRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\SettingChanges;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Settings → eCommerce (Doc 19 section 19.37 - Phase A1): the business's own
 * rules for its storefronts - guest checkout, verifying a customer's e-mail,
 * prices shown with tax or without it, and the prefix of its online orders'
 * numbers (in capitals). Settings never saved read as their defaults. Each
 * save is logged with what it changed. Phase F4 adds Review Orders Above
 * (a total in the base currency, none to start) and holding orders that may
 * want a look as they are placed (off).
 */
class EcommerceSettingService
{
    /** What the settings form saves. */
    private const SAVED = ['guest_checkout', 'verify_customer_email', 'price_display', 'order_prefix', 'cookie_notice', 'cookie_notice_text', 'cart_days', 'checkout_phone', 'checkout_notes', 'review_above', 'hold_for_review'];

    public function __construct(
        protected EcommerceSettingRepository $settings,
    ) {}

    public function show(Workspace $workspace): EcommerceSetting
    {
        return $this->settings->forWorkspace($workspace);
    }

    /** $data is validated (see SaveEcommerceSettingsRequest). */
    public function save(Workspace $workspace, array $data): EcommerceSetting
    {
        $fields = Arr::only($data, self::SAVED);
        $fields['order_prefix'] = Str::upper(trim($fields['order_prefix']));

        // Phase C6b: the notice's words, blank for izSuite's own.
        if (array_key_exists('cookie_notice_text', $fields)) {
            $fields['cookie_notice_text'] = trim((string) $fields['cookie_notice_text']) ?: null;
        }

        $settings = $this->settings->save($workspace, $fields);

        ActivityLogService::record('updated', 'Updated the eCommerce settings', $workspace, ['source' => 'api', 'changes' => SettingChanges::of($settings)]);

        return $settings;
    }
}
