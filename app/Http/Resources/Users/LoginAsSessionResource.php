<?php

namespace App\Http\Resources\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Marks a session an admin started from the admin panel's "Login as": the
 * website shows its "Back to admin" button only while it holds one. Wraps the
 * admin panel URL the button goes back to.
 */
class LoginAsSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'back_url' => $this->resource,
        ];
    }
}
