<?php

namespace App\Repositories\Admin;

use App\Models\Admin\ServiceEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/** What outside services did (Doc 15 Phase D3a): webhooks taken or refused, and calls that failed. */
class ServiceEventRepository
{
    public function record(string $service, string $kind, bool $ok, ?string $message = null): void
    {
        ServiceEvent::create(['service' => $service, 'kind' => $kind, 'ok' => $ok, 'message' => $message === null ? null : Str::limit($message, 490)]);
    }

    /** $service's latest $kind, however it went. */
    public function latest(string $service, string $kind): ?ServiceEvent
    {
        return ServiceEvent::where('service', $service)->where('kind', $kind)->latest('id')->first();
    }

    /** How many of $service's $kind failed since $since. */
    public function failures(string $service, string $kind, CarbonInterface $since): int
    {
        return ServiceEvent::where('service', $service)->where('kind', $kind)->where('ok', false)->where('created_at', '>=', $since)->count();
    }

    /** @return Collection<int, ServiceEvent> $service's latest failures, of any kind */
    public function latestFailures(string $service, int $limit): Collection
    {
        return ServiceEvent::where('service', $service)->where('ok', false)->latest('id')->limit($limit)->get();
    }
}
