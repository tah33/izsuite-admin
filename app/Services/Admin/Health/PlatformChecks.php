<?php

namespace App\Services\Admin\Health;

use App\Repositories\Admin\ServiceHealthRepository;
use App\Support\Bytes;
use App\Support\ServiceStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * What izSuite runs on, as Admin > Service Health checks it (Doc 15 section
 * 15.23 - Phase D3a): the app, the scheduler and the queue's workers (by
 * their heartbeats), the database, the cache, file storage and the PDF
 * maker - each asked afresh.
 */
class PlatformChecks
{
    /** Minutes without a scheduler run, or a job a worker ran, before it is failing. */
    public const SCHEDULER_LATE = 5;

    public const WORKER_LATE    = 10;

    /** Milliseconds the database may take to answer before it is slow. */
    public const SLOW_MS        = 500;

    /** Share of the disk left free below which storage warns. */
    public const FREE_PERCENT   = 10;

    public function __construct(
        private ServiceHealthRepository $health,
        private Heartbeats $heartbeats,
    ) {}

    /** @return list<ServiceStatus> */
    public function all(): array
    {
        return [$this->app(), $this->scheduler(), $this->queue(), $this->database(), $this->cache(), $this->storage(), $this->pdf()];
    }

    private function app(): ServiceStatus
    {
        return new ServiceStatus('app', ServiceStatus::OK, 'It answers: this page came from it.', [
            'Environment' => (string) config('app.env'),
            'PHP'         => PHP_VERSION,
            'Laravel'     => app()->version(),
        ]);
    }

    private function scheduler(): ServiceStatus
    {
        $last  = $this->heartbeats->last(Heartbeats::SCHEDULER);
        $facts = ['Last run' => $last?->diffForHumans() ?? 'Never seen'];
        $waits = 'renewals, closures, usage snapshots and the workers\' heartbeat wait for it.';

        return match (true) {
            $last === null                                         => new ServiceStatus('scheduler', ServiceStatus::FAILING, "It has not been seen running: {$waits}", $facts),
            $last->lt(now()->subMinutes(self::SCHEDULER_LATE))     => new ServiceStatus('scheduler', ServiceStatus::FAILING, "It has not run for {$last->diffForHumans(null, true)}: {$waits}", $facts),
            default                                                => new ServiceStatus('scheduler', ServiceStatus::OK, 'It runs each minute.', $facts),
        };
    }

    private function queue(): ServiceStatus
    {
        try {
            $waiting = $this->health->waitingJobs();
        } catch (\Throwable $e) {
            report($e);
            $waiting = null;
        }

        $worker    = $this->heartbeats->last(Heartbeats::QUEUE);
        $scheduled = $this->heartbeats->last(Heartbeats::SCHEDULER)?->gte(now()->subMinutes(self::SCHEDULER_LATE)) ?? false;
        $failed    = $this->health->failedJobsSince(now()->subDay());
        $facts     = [
            'Waiting now'            => $waiting === null ? 'Queue not reached' : (string) $waiting,
            'A worker last ran a job' => $worker?->diffForHumans() ?? 'Never seen',
            'Failed in 24 hours'     => (string) $failed,
            'Failed jobs kept'       => (string) $this->health->failedJobsKept(),
        ];

        return match (true) {
            $waiting === null                                                  => new ServiceStatus('queue', ServiceStatus::FAILING, 'The queue cannot be reached: nothing waiting is run.', $facts),
            // The workers' heartbeat comes through the scheduler: without it, it says nothing.
            $scheduled && ($worker === null || $worker->lt(now()->subMinutes(self::WORKER_LATE))) => new ServiceStatus('queue', ServiceStatus::FAILING, 'No worker has run a job lately: e-mails, imports and new accounts\' set-up wait.', $facts),
            $failed > 0                                                        => new ServiceStatus('queue', ServiceStatus::WARNING, "{$failed} ".Str::plural('job', $failed).' failed in the last 24 hours.', $facts),
            default                                                            => new ServiceStatus('queue', ServiceStatus::OK, 'Workers are running the jobs.', $facts),
        };
    }

    private function database(): ServiceStatus
    {
        try {
            $ping = $this->health->ping();
        } catch (\Throwable $e) {
            report($e);

            return new ServiceStatus('database', ServiceStatus::FAILING, 'It cannot be reached: '.Str::limit($e->getMessage(), 160), []);
        }

        $facts = ['Answered in' => "{$ping['milliseconds']} ms", 'Database' => trim($ping['driver'].' '.$ping['version'])];

        return $ping['milliseconds'] > self::SLOW_MS
            ? new ServiceStatus('database', ServiceStatus::WARNING, "It answered slowly, in {$ping['milliseconds']} ms.", $facts)
            : new ServiceStatus('database', ServiceStatus::OK, 'It answers.', $facts);
    }

    private function cache(): ServiceStatus
    {
        $facts = ['Store' => (string) config('cache.default'), 'Sessions' => (string) config('session.driver')];

        try {
            $token = Str::random(16);
            Cache::put('health:probe', $token, 60);
            $back  = Cache::pull('health:probe');
        } catch (\Throwable $e) {
            report($e);

            return new ServiceStatus('cache', ServiceStatus::FAILING, 'It cannot be reached: '.Str::limit($e->getMessage(), 160), $facts);
        }

        return $back === $token
            ? new ServiceStatus('cache', ServiceStatus::OK, 'It keeps what it is given.', $facts)
            : new ServiceStatus('cache', ServiceStatus::FAILING, 'It did not give back what it was given.', $facts);
    }

    private function storage(): ServiceStatus
    {
        $disk  = (string) config('files.storage');
        $facts = ['Files are kept on' => $disk];

        // At the disk's root, so no folder is left behind.
        try {
            $token = Str::random(16);
            Storage::disk($disk)->put('.health-probe', $token);
            $back  = Storage::disk($disk)->get('.health-probe');
            Storage::disk($disk)->delete('.health-probe');
        } catch (\Throwable $e) {
            report($e);

            return new ServiceStatus('storage', ServiceStatus::FAILING, 'Files cannot be written: '.Str::limit($e->getMessage(), 160), $facts);
        }

        if ($back !== $token) {
            return new ServiceStatus('storage', ServiceStatus::FAILING, 'A file written could not be read back.', $facts);
        }

        // On the server's own disk, how much room is left.
        $root  = config("filesystems.disks.{$disk}.driver") === 'local' ? config("filesystems.disks.{$disk}.root") : null;
        $free  = $root ? @disk_free_space($root) : false;
        $total = $root ? @disk_total_space($root) : false;

        if ($free !== false && $total) {
            $percent         = round($free / $total * 100, 1);
            $facts['Free']   = Bytes::format((int) $free).' of '.Bytes::format((int) $total)." ({$percent}%)";

            if ($percent < self::FREE_PERCENT) {
                return new ServiceStatus('storage', ServiceStatus::WARNING, "Only {$percent}% of the disk is free.", $facts);
            }
        }

        return new ServiceStatus('storage', ServiceStatus::OK, 'Files are written, read back and removed.', $facts);
    }

    private function pdf(): ServiceStatus
    {
        $url   = rtrim((string) config('services.gotenberg.url'), '/');
        $facts = ['Address' => $url];

        try {
            $response = Http::timeout(3)->get("{$url}/health");
        } catch (\Throwable $e) {
            return new ServiceStatus('pdf', ServiceStatus::FAILING, 'It cannot be reached: invoices, receipts and documents get no PDF.', $facts);
        }

        return $response->successful() && $response->json('status') === 'up'
            ? new ServiceStatus('pdf', ServiceStatus::OK, 'It answers: PDFs can be made.', $facts)
            : new ServiceStatus('pdf', ServiceStatus::FAILING, "It answered {$response->status()}: PDFs may not be made.", $facts);
    }
}
