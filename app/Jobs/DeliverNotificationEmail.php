<?php

namespace App\Jobs;

use App\Services\Shared\Notifications\Notifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * A notification's e-mail (Doc 14 section 15 - Phase B1), tried three
 * times; still failing, its delivery is kept as failed with the reason.
 */
class DeliverNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> seconds before the second and third tries */
    public array $backoff = [60, 300];

    public function __construct(
        public int $deliveryId,
    ) {}

    public function handle(Notifier $notifier): void
    {
        $notifier->deliverEmail($this->deliveryId);
    }

    public function failed(Throwable $e): void
    {
        app(Notifier::class)->emailFailed($this->deliveryId, $e);
    }
}
