<?php

namespace App\Http\Resources\Billing;

use App\Models\User\Location;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the owner chooses from to bring the business within its plan (Doc 13
 * section 14; see OverLimitService::choices()): the plan, and for each limit
 * passed its number and who or what may stay - the owner and the Primary
 * Location marked, as they always do. A limit not passed is null.
 *
 * @property Workspace $resource
 */
class OverLimitChoicesResource extends JsonResource
{
    public function __construct(Workspace $workspace, private array $choices)
    {
        parent::__construct($workspace);
    }

    public function toArray(Request $request): array
    {
        $users     = $this->choices['users'];
        $locations = $this->choices['locations'];

        return [
            'package'   => $this->choices['package'],
            'users'     => $users ? [
                'limit'   => $users['limit'],
                'members' => $users['members']->map(fn (WorkspaceMember $member) => [
                    'id'     => $member->id,
                    'name'   => $member->name,
                    'email'  => $member->email,
                    'role'   => $member->role?->name,
                    'status' => $member->status,
                    'owner'  => $member->isOwnerOf($this->resource),
                ])->all(),
            ] : null,
            'locations' => $locations ? [
                'limit'     => $locations['limit'],
                'locations' => $locations['locations']->map(fn (Location $location) => [
                    'id'      => $location->id,
                    'name'    => $location->name,
                    'primary' => $location->is_primary,
                ])->all(),
            ] : null,
        ];
    }
}
