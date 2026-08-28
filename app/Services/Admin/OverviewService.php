<?php

namespace App\Services\Admin;

use App\Models\Admin\ContactMessage;
use App\Repositories\User\UserRepository;

class OverviewService
{
    public function __construct(
        protected UserRepository $userRepo,
    ) {}

    public function getOverviewData(): array
    {
        return [
            'stats'     => $this->getStats(),
            'chartData' => $this->userRepo->getGrowthHistory(),
        ];
    }

    private function getStats(): array
    {
        $totalUsers = $this->userRepo->getTotalCount();

        return [
            'total_users'     => $totalUsers,
            'active_users'    => $this->userRepo->getActiveCount(),
            'pro_subscribers' => 0,
            'mrr'             => 0,
            'new_messages'    => ContactMessage::where('status', 'new')->count(),
        ];
    }
}
