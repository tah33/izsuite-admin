<?php

use App\Models\User\Import;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Imports left a week ago - finished or not - with their uploaded files.
Schedule::command('model:prune', ['--model' => [Import::class]])->daily();
