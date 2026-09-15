<?php

namespace App\Providers;

use App\Events\RecruiterNotificationRequested;
use App\Listeners\QueueRecruiterNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(RecruiterNotificationRequested::class, QueueRecruiterNotification::class);

        $this->configurePasswordPolicy();
        $this->configureRateLimiters();
    }

    /**
     * One password policy for the whole product.
     *
     * Every place a password is set - admin profile, staff create and update,
     * and API registration - validates with Password::defaults(), so this is
     * the single place to loosen or tighten the rules.
     *
     * Only setting a password is gated. Existing accounts keep signing in with
     * what they have and are held to the policy the next time they change it.
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());
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

        // Two ceilings, because one alone is easy to walk around: the IP limit
        // stops a fast burst, the per-address limit stops a slow grind through
        // the code space from a pool of addresses.
        RateLimiter::for('verify-email', fn (Request $request) => [
            Limit::perMinute(6)->by('verify-email-ip|'.$request->ip()),
            Limit::perHour(12)->by('verify-email|'.$this->emailKey($request)),
        ]);

        RateLimiter::for('resend-verification', fn (Request $request) => [
            Limit::perMinute(3)->by('resend-ip|'.$request->ip()),
            Limit::perHour(6)->by('resend|'.$this->emailKey($request)),
        ]);
    }

    /**
     * Limiters run before the FormRequest, so the address still arrives in
     * whatever casing the caller used. Folding it here stops "Bob@x.com" and
     * "bob@x.com" from getting a bucket each.
     */
    private function emailKey(Request $request): string
    {
        return strtolower(trim((string) $request->input('email')));
    }
}
