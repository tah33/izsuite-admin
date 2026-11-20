<?php

namespace App\Services\Api\Reports;

use App\Models\User\Workspace;
use App\Repositories\User\PosRegisterRepository;
use App\Repositories\User\PosReportRepository;
use App\Services\Shared\WorkspaceAccess;

/**
 * What POS's reports can be narrowed to (Doc 18 section 18.36 - Phase H1),
 * at the locations the user may use: the registers, and the cashiers -
 * those of the registers, and whoever sold or took a return there.
 */
class PosReportChoiceService
{
    public function __construct(
        protected PosRegisterRepository $registers,
        protected PosReportRepository $pos,
        protected WorkspaceAccess $access,
    ) {}

    /** @return array{registers: \Illuminate\Support\Collection, cashiers: \Illuminate\Support\Collection} */
    public function choices(Workspace $workspace): array
    {
        $locations = $this->access->locations();

        return [
            'registers' => $this->registers->getFor($workspace, $locations),
            'cashiers'  => $this->pos->cashiers($workspace, $locations),
        ];
    }
}
