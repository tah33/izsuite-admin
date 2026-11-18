<?php

namespace App\Http\Resources\Profile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What setting up two-factor authentication shows (Doc 12 section 22): the
 * authenticator app's secret, to type in, and the otpauth:// address the QR
 * code holds.
 *
 * @property array{secret: string, uri: string} $resource
 */
class TwoFactorSetupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'secret' => $this->resource['secret'],
            'uri'    => $this->resource['uri'],
        ];
    }
}
