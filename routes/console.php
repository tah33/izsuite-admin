<?php

use App\Models\Admin\ServiceEvent;
use App\Models\Shared\IdempotencyKey;
use App\Models\User\Import;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Imports left a week ago - finished or not - with their uploaded files; create requests' keys a day old (Doc 14 Phase A);
// outside services' events after 30 days (Doc 15 Phase D3a).
Schedule::command('model:prune', ['--model' => [Import::class, IdempotencyKey::class, ServiceEvent::class]])->daily();

// The scheduler's heartbeat, and a queued one for the workers, for Admin > Service Health (Doc 15 Phase D3a).
Schedule::command('health:heartbeat')->everyMinute();

// Subscriptions' renewals, grace and trials, each business by its own day (Doc 13 Phase C2a).
Schedule::command('subscriptions:advance')->hourly()->withoutOverlapping();

// Plan versions whose day has come, by UTC (Doc 15 Phase E3b).
Schedule::command('plans:advance')->hourly()->withoutOverlapping();

// Versions of a country's defaults whose day has come, by UTC (Doc 15 Phase F3a).
Schedule::command('countries:advance')->hourly()->withoutOverlapping();

// Privacy requests' data exports whose link has expired, deleted (Doc 15 Phase G1).
Schedule::command('privacy:purge-exports')->hourly()->withoutOverlapping();

// The plan's limits nearly reached, reached or passed, each state once (Doc 14 Phase B2).
Schedule::command('notifications:usage')->hourly()->withoutOverlapping();

// Businesses closed on their scheduled closing day (Doc 15 Phase B1).
Schedule::command('businesses:close-due')->hourly()->withoutOverlapping();

// Each business's usage today, for Admin > Usage's history (Doc 15 Phase D2), late in the UTC day.
Schedule::command('usage:snapshot')->dailyAt('23:50')->timezone('UTC')->withoutOverlapping();

// Support sessions past their end, recorded as ended in the business's Activity Log (Doc 15 Phase B4).
Schedule::command('support-sessions:close-expired')->everyFiveMinutes()->withoutOverlapping();
