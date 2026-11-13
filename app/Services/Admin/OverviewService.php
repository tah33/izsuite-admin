<?php

namespace App\Services\Admin;

use App\Repositories\Admin\ContactMessageRepository;
use App\Repositories\User\UserRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\Artisan;

class OverviewService
{
    public function __construct(
        protected UserRepository $userRepo,
        protected ContactMessageRepository $contactMessageRepo,
    ) {}

    public function getOverviewData(): array
    {
        return [
            'stats'     => $this->getStats(),
            'chartData' => $this->userRepo->getGrowthHistory(),
        ];
    }

    /**
     * Clears the application's caches: what went wrong, when something did.
     */
    public function clearCache(): ?string
    {
        try {
            Artisan::call('optimize:clear');
        } catch (\Exception $e) {
            app(AdminAuditLogService::class)->record('cleared', 'Tried to clear the application cache', null, ['error' => $e->getMessage()], null, 'failed');

            return 'Failed to clear cache: '.$e->getMessage();
        }

        ActivityLogService::record('cleared', 'Cleared the application cache');

        return null;
    }

    private function getStats(): array
    {
        $totalUsers = $this->userRepo->getTotalCount();

        return [
            'total_users'     => $totalUsers,
            'active_users'    => $this->userRepo->getActiveCount(),
            'pro_subscribers' => 0,
            'mrr'             => 0,
            'new_messages'    => $this->contactMessageRepo->countNew(),
        ];
    }
}
