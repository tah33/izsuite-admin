<?php

namespace App\Http\Resources\Notifications;

use App\Support\NotificationKinds;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Which notifications go in the app and by e-mail, kind by kind (Doc 11 and
 * Doc 12 section 22 - Doc 14 Phase B2): what the business sends, or what a
 * user receives. A kind that is always on says so.
 *
 * @property array<string, array{in_app: bool, email: bool}> $resource
 */
class NotificationPreferencesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kinds' => array_map(fn (string $kind) => [
                'key'         => $kind,
                'label'       => NotificationKinds::KINDS[$kind][0],
                'description' => NotificationKinds::KINDS[$kind][1],
                'locked'      => NotificationKinds::locked($kind),
                'in_app'      => $this->resource[$kind]['in_app'],
                'email'       => $this->resource[$kind]['email'],
            ], array_keys(NotificationKinds::KINDS)),
        ];
    }
}
