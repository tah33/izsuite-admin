<?php

use App\Models\Shared\IdempotencyKey;
use App\Models\User\Import;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Imports left a week ago - finished or not - with their uploaded files; create requests' keys a day old (Doc 14 Phase A).
Schedule::command('model:prune', ['--model' => [Import::class, IdempotencyKey::class]])->daily();

// Subscriptions' renewals, grace and trials, each business by its own day (Doc 13 Phase C2a).
Schedule::command('subscriptions:advance')->hourly()->withoutOverlapping();

// The plan's limits nearly reached, reached or passed, each state once (Doc 14 Phase B2).
Schedule::command('notifications:usage')->hourly()->withoutOverlapping();

// Businesses closed on their scheduled closing day (Doc 15 Phase B1).
Schedule::command('businesses:close-due')->hourly()->withoutOverlapping();
