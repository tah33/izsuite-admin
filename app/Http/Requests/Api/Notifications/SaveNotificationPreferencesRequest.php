<?php

namespace App\Http\Requests\Api\Notifications;

use App\Support\NotificationKinds;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Every kind of notification, in the app and by e-mail, on or off (Doc 14
 * Phase B2). A kind that is always on may be sent as anything: it stays on.
 */
class SaveNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $kinds = array_keys(NotificationKinds::KINDS);
        $rules = ['preferences' => ['required', 'array:'.implode(',', $kinds)]];

        foreach ($kinds as $kind) {
            $rules["preferences.{$kind}"]        = ['required', 'array:in_app,email'];
            $rules["preferences.{$kind}.in_app"] = ['required', 'boolean'];
            $rules["preferences.{$kind}.email"]  = ['required', 'boolean'];
        }

        return $rules;
    }
}
