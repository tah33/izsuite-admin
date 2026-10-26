<?php

namespace App\Http\Resources\BusinessSetup;

use App\Models\User\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Settings > Subscription, and the upload panels' warning: the business's
 * package, and its storage as StorageUsage::summary() measures it.
 *
 * @property Workspace $resource
 */
class BusinessUsageResource extends JsonResource
{
    public function __construct(Workspace $workspace, private array $storage)
    {
        parent::__construct($workspace);
    }

    public function toArray(Request $request): array
    {
        return [
            'package' => $this->package ? ['name' => $this->package->name] : null,
            'storage' => [
                'used_bytes'  => $this->storage['used_bytes'],
                'limit_bytes' => $this->storage['limit_bytes'],
                'percent'     => $this->storage['percent'],
                'state'       => $this->storage['state'],
            ],
        ];
    }
}
