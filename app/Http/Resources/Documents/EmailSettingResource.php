<?php

namespace App\Http\Resources\Documents;

use App\Models\User\EmailSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Settings > Email as the page edits it: the sender name and reply-to set -
 * null for the defaults, which come too - the language, and each kind's
 * email, the business's own or the default. Wraps EmailSettingService::get().
 */
class EmailSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $setting = $this->resource['setting'];

        return [
            'sender_name'         => $setting->sender_name,
            'reply_to'            => $setting->reply_to,
            'default_sender_name' => $this->resource['sender_name'],
            'default_reply_to'    => $this->resource['reply_to'],
            'language'            => $setting->language,
            'templates'           => collect(array_keys(EmailSetting::DEFAULT_TEMPLATES))->mapWithKeys(fn (string $key) => [$key => $setting->template($key)])->all(),
            'placeholders'        => EmailSetting::PLACEHOLDERS,
        ];
    }
}
