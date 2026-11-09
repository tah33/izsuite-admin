<?php

namespace App\Http\Requests\Admin;

use App\Repositories\Admin\PaymentMethodRepository;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id     = (int) $this->route('id');
        $method = app(PaymentMethodRepository::class)->find($id);

        if (! $method) {
            return [];
        }

        // Stripe's keys, test and live apart (Doc 13 Phase D2): each by its own prefix. Left blank, the kept one stays.
        if ($method->type === 'online') {
            $rules = ['is_active' => ['nullable'], 'is_sandbox' => ['nullable'], 'credentials' => ['nullable', 'array']];

            foreach (['test', 'live'] as $mode) {
                $rules["credentials.{$mode}.publishable_key"] = ['nullable', 'string', 'max:255', "starts_with:pk_{$mode}_"];
                $rules["credentials.{$mode}.secret_key"]      = ['nullable', 'string', 'max:255', "starts_with:sk_{$mode}_,rk_{$mode}_"];
                $rules["credentials.{$mode}.webhook_secret"]  = ['nullable', 'string', 'max:255', 'starts_with:whsec_'];
            }

            return $rules;
        }

        return [
            'name'         => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'instructions' => ['nullable', 'string'],
            'image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
            'is_active'    => ['nullable'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        $names = [];

        foreach (['test', 'live'] as $mode) {
            $names["credentials.{$mode}.publishable_key"] = "{$mode} publishable key";
            $names["credentials.{$mode}.secret_key"]      = "{$mode} secret key";
            $names["credentials.{$mode}.webhook_secret"]  = "{$mode} webhook signing secret";
        }

        return $names;
    }
}
