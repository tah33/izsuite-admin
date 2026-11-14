<?php

namespace App\Jobs;

use App\Jobs\Concerns\SafeToRetry;
use App\Repositories\Admin\PrivacyRequestRepository;
use App\Repositories\User\UserRepository;
use App\Services\Admin\Privacy\PrivacyRequestService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Builds the data export of a privacy request (Doc 15 Phase G1) in the
 * background - a large business takes a while - then e-mails its link to
 * the person who asked. Safe to run again: a request no longer waiting for
 * its export is left alone. One that fails goes back to checked, to be
 * started again.
 */
class BuildPrivacyExport implements SafeToRetry, ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public int $requestId,
        public ?int $adminId,
    ) {}

    public function handle(PrivacyRequestService $service, PrivacyRequestRepository $requests, UserRepository $users): void
    {
        $service->buildExport($requests->findOrFail($this->requestId), $users->findById($this->adminId));
    }

    public function failed(\Throwable $e): void
    {
        $request = app(PrivacyRequestRepository::class)->findOrFail($this->requestId);

        app(PrivacyRequestService::class)->exportFailed($request, $e->getMessage(), app(UserRepository::class)->findById($this->adminId));
    }
}
