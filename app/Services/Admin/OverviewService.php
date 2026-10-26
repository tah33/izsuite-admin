<?php

namespace App\Services\Admin;

use App\Repositories\Admin\ContactMessageRepository;
use App\Repositories\User\UserRepository;
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
            return 'Failed to clear cache: '.$e->getMessage();
        }

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
