<?php

namespace App\Http\Resources\BusinessSetup;

use App\Models\User\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Settings > Subscription, and the upload panels' warning: the business's
 * package, its storage as StorageUsage::summary() measures it, and its users
 * and locations against the package's limits (see PlanLimits::usage()) - a
 * limit of null is none.
 *
 * @property Workspace $resource
 */
class BusinessUsageResource extends JsonResource
{
    public function __construct(Workspace $workspace, private array $usage)
    {
        parent::__construct($workspace);
    }

    public function toArray(Request $request): array
    {
        $storage = $this->usage['storage'];

        return [
            'package'   => $this->package ? ['name' => $this->package->name] : null,
            'storage'   => [
                'used_bytes'  => $storage['used_bytes'],
                'limit_bytes' => $storage['limit_bytes'],
                'percent'     => $storage['percent'],
                'state'       => $storage['state'],
            ],
            'users'     => $this->usage['users'],
            'locations' => $this->usage['locations'],
        ];
    }
}
