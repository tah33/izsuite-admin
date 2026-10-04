<?php

namespace App\Services\Api\Staff;

use App\Repositories\Admin\StaffRepository;
use Illuminate\Database\Eloquent\Collection;

class StaffService
{
    public function __construct(
        protected StaffRepository $staffRepository,
    ) {}

    /**
     * The active staff accounts, for the Workspace form's Staff dropdown.
     */
    public function list(): Collection
    {
        return $this->staffRepository->getActive();
    }
}
