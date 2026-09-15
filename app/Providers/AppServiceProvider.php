<?php

namespace App\Providers;

use App\Events\RecruiterNotificationRequested;
use App\Listeners\QueueRecruiterNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(RecruiterNotificationRequested::class, QueueRecruiterNotification::class);

        $this->configureRateLimiters();
    }

    /**
     * One bucket per auth endpoint.
     *
     * A bare `throttle:10,1` looks per-route but is not: for a guest, Laravel
     * builds the signature from the domain and IP alone - the route is never in
     * it - so every unauthenticated endpoint using that syntax shares a single
     * counter. Login and register would then eat each other's budget: create a
     * few accounts and you are locked out of signing in, with a 429 that blames
     * the wrong endpoint.
     *
     * Naming the limiter lets each one key itself, which is what keeps them
     * independent.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by('login|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(10)->by('register|'.$request->ip()));
    }
}
