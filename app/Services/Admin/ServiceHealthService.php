<?php

namespace App\Services\Admin;

use App\Jobs\Concerns\SafeToRetry;
use App\Models\Admin\ServiceEvent;
use App\Models\User\User;
use App\Repositories\Admin\IncidentRepository;
use App\Repositories\Admin\ServiceEventRepository;
use App\Repositories\Admin\ServiceHealthRepository;
use App\Services\Admin\Health\ActivityChecks;
use App\Services\Admin\Health\PlatformChecks;
use App\Services\Shared\ActivityLogService;
use App\Support\ServiceStatus;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Service Health (Doc 15 section 15.23 - Phase D3a): each service
 * izSuite runs on or uses - OK, Warning, Failing or Not used - with what
 * failed behind it; a failed job run again only where it is safe (it says
 * so: SafeToRetry), never sending, adding or charging twice; and the
 * alerts the Overview shows.
 */
class ServiceHealthService
{
    /** How many of each kind of failure the page lists. */
    public const LATEST = 15;

    public function __construct(
        protected PlatformChecks $platform,
        protected ActivityChecks $activity,
        protected ServiceHealthRepository $health,
        protected ServiceEventRepository $events,
        protected IncidentRepository $incidents,
    ) {}

    /** @return list<ServiceStatus> every service, as found now */
    public function statuses(): array
    {
        return [...$this->platform->all(), ...$this->activity->all()];
    }

    /** @return list<ServiceStatus> the services warning or failing */
    public function alerts(): array
    {
        return array_values(array_filter($this->statuses(), fn (ServiceStatus $status) => $status->alerting()));
    }

    /** Everything the page shows: the services, and the latest failures behind them. */
    public function report(): array
    {
        return [
            'statuses'           => $this->statuses(),
            'checkedAt'          => now(),
            'jobs'               => $this->health->latestFailedJobs(self::LATEST)->map(fn (object $job) => $this->describe($job))->all(),
            'notificationEmails' => $this->health->latestFailedNotificationEmails(self::LATEST),
            'documentEmails'     => $this->health->latestFailedDocumentEmails(self::LATEST),
            'imports'            => $this->health->latestFailedImports(self::LATEST),
            'providerFailures'   => $this->events->latestFailures(ServiceEvent::STRIPE, self::LATEST),
            // Incidents not resolved yet (Phase D3b).
            'incidents'          => $this->incidents->unresolved(),
        ];
    }

    /** Puts failed job $uuid back on its queue - refused, on `job`, when it is not safe to run twice. Its name. */
    public function retry(string $uuid, User $admin): string
    {
        $job = $this->describe($this->health->findFailedJob($uuid) ?? abort(404));

        if (! $job['safe']) {
            throw ValidationException::withMessages(['job' => "{$job['name']} is not run again here: {$job['why']}"]);
        }

        $this->health->retryFailedJob($uuid);

        ActivityLogService::record('retried', "Ran the failed job {$job['name']} again", null, ['job' => $uuid], $admin);

        return $job['name'];
    }

    /** Takes failed job $uuid off the list, not run. Its name. */
    public function forget(string $uuid, User $admin): string
    {
        $job = $this->describe($this->health->findFailedJob($uuid) ?? abort(404));

        $this->health->forgetFailedJob($uuid);

        ActivityLogService::record('deleted', "Forgot the failed job {$job['name']}", null, ['job' => $uuid], $admin);

        return $job['name'];
    }

    /**
     * A failed job as the page shows it: what it is, where it ran, why it
     * failed, when, and whether it may run again - with why not.
     *
     * @return array{uuid: string, name: string, queue: string, connection: string, error: string, failed_at: Carbon, safe: bool, why: ?string}
     */
    private function describe(object $job): array
    {
        $payload = json_decode((string) $job->payload, true) ?: [];
        $command = $payload['data']['commandName'] ?? null;
        $safe    = $command !== null && class_exists($command) && is_subclass_of($command, SafeToRetry::class);

        return [
            'uuid'       => (string) $job->uuid,
            'name'       => class_basename($payload['displayName'] ?? $command ?? 'Unknown job'),
            'queue'      => (string) $job->queue,
            'connection' => (string) $job->connection,
            'error'      => Str::limit(trim((string) strtok((string) $job->exception, "\n")), 300),
            'failed_at'  => Carbon::parse($job->failed_at),
            'safe'       => $safe,
            'why'        => match (true) {
                $safe                                   => null,
                $command === SendQueuedMailable::class  => 'the mail may have gone out. Send it again from where it was sent, if it did not.',
                default                                 => 'it does not say it is safe to run twice.',
            },
        ];
    }
}
