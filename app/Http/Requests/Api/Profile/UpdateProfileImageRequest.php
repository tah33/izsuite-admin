<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;

/** A new profile image for the signed-in user (Doc 12 section 22): a photo of up to 2 MB. */
class UpdateProfileImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.mimes' => 'Choose a JPG, PNG or WebP image.',
            'image.max'   => 'Choose an image of 2 MB or less.',
        ];
    }
}
