<?php

namespace App\Repositories\Admin;

use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\AdminSignIn;
use App\Models\Shared\ActivityLog;
use App\Models\Shared\NotificationDelivery;
use App\Models\User\DocumentEmail;
use App\Models\User\Import;
use App\Models\User\User;
use App\Services\Api\Auth\AuthService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * What Admin > Service Health reads (Doc 15 section 15.23 - Phase D3a): the
 * database's answer, the queue and its failed jobs - retried or forgotten
 * here - failed e-mails and imports, and sign-ins that failed.
 */
class ServiceHealthRepository
{
    /** @return array{milliseconds: float, driver: string, version: ?string} how long the database took to answer */
    public function ping(): array
    {
        $start = hrtime(true);
        DB::select('select 1');
        $took  = (hrtime(true) - $start) / 1e6;
        $pdo   = DB::connection()->getPdo();

        return ['milliseconds' => round($took, 1), 'driver' => DB::connection()->getDriverName(), 'version' => (string) $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) ?: null];
    }

    /** Jobs waiting on the default queue now. */
    public function waitingJobs(): int
    {
        return Queue::size();
    }

    public function failedJobsSince(CarbonInterface $since): int
    {
        return DB::table('failed_jobs')->where('failed_at', '>=', $since)->count();
    }

    public function failedJobsKept(): int
    {
        return DB::table('failed_jobs')->count();
    }

    /** @return SupportCollection<int, object> the latest failed jobs: uuid, connection, queue, payload, exception, failed_at */
    public function latestFailedJobs(int $limit): SupportCollection
    {
        return DB::table('failed_jobs')->latest('id')->limit($limit)->get(['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at']);
    }

    public function findFailedJob(string $uuid): ?object
    {
        return DB::table('failed_jobs')->where('uuid', $uuid)->first(['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at']);
    }

    /** Puts the failed job back on its queue, its tries reset, and takes it off the list. */
    public function retryFailedJob(string $uuid): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
    }

    public function forgetFailedJob(string $uuid): void
    {
        app('queue.failer')->forget($uuid);
    }

    public function failedNotificationEmails(CarbonInterface $since): int
    {
        return NotificationDelivery::where('channel', NotificationDelivery::EMAIL)->where('status', NotificationDelivery::FAILED)->where('updated_at', '>=', $since)->count();
    }

    /** @return Collection<int, NotificationDelivery> the latest, each with its notification and who it was for */
    public function latestFailedNotificationEmails(int $limit): Collection
    {
        return NotificationDelivery::with('notification.user:id,email')
            ->where('channel', NotificationDelivery::EMAIL)
            ->where('status', NotificationDelivery::FAILED)
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    public function failedDocumentEmails(CarbonInterface $since): int
    {
        return DocumentEmail::where('status', DocumentEmail::FAILED)->where('updated_at', '>=', $since)->count();
    }

    /** @return Collection<int, DocumentEmail> the latest, each with its business */
    public function latestFailedDocumentEmails(int $limit): Collection
    {
        return DocumentEmail::with('workspace:id,name')->where('status', DocumentEmail::FAILED)->latest('updated_at')->limit($limit)->get();
    }

    public function failedImports(CarbonInterface $since): int
    {
        return Import::where('status', Import::FAILED)->where('updated_at', '>=', $since)->count();
    }

    /** Imports checking or adding rows that have not moved since $before. */
    public function stalledImports(CarbonInterface $before): int
    {
        return Import::whereIn('status', [Import::CHECKING, Import::IMPORTING])->where('updated_at', '<', $before)->count();
    }

    /** @return Collection<int, Import> the latest failed imports, each with its business */
    public function latestFailedImports(int $limit): Collection
    {
        return Import::with('workspace:id,name')->where('status', Import::FAILED)->latest('updated_at')->limit($limit)->get(['id', 'workspace_id', 'kind', 'file_name', 'error', 'updated_at']);
    }

    /** Website accounts' sign-ins that failed since $since: a wrong password or code. */
    public function failedAccountSignIns(CarbonInterface $since): int
    {
        return ActivityLog::where('action', AuthService::FAILED_SIGN_IN)->where('model_type', User::class)->where('created_at', '>=', $since)->count();
    }

    /** Accounts with $times failed sign-ins or more since $since. */
    public function accountsFailingSignIn(CarbonInterface $since, int $times): int
    {
        return ActivityLog::where('action', AuthService::FAILED_SIGN_IN)
            ->where('model_type', User::class)
            ->where('created_at', '>=', $since)
            ->groupBy('model_id')
            ->havingRaw('count(*) >= ?', [$times])
            ->select('model_id')
            ->get()
            ->count();
    }

    public function failedAdminSignIns(CarbonInterface $since): int
    {
        return AdminSignIn::where('succeeded', false)->where('created_at', '>=', $since)->count();
    }

    public function lockedAccounts(): int
    {
        return User::where('status', User::LOCKED)->count();
    }

    /** Admin pages or actions a role did not allow, refused since $since. */
    public function refusedAdminActions(CarbonInterface $since): int
    {
        return AdminAuditLog::where('result', 'refused')->where('created_at', '>=', $since)->count();
    }
}
