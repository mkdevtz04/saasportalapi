<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Services\Sms\BeemSmsGateway;
use App\Services\Sms\KilakonaSmsGateway;
use App\Services\Sms\LogSmsGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, function () {
            return match (config('sms.driver')) {
                'beem'  => new BeemSmsGateway(
                    (string) config('sms.beem.api_key'),
                    (string) config('sms.beem.secret'),
                    (string) config('sms.sender_id'),
                ),
                'kilakona' => new KilakonaSmsGateway(
                    (string) config('sms.kilakona.api_key'),
                    (string) config('sms.kilakona.secret'),
                    (string) config('sms.sender_id'),
                    (string) config('sms.kilakona.endpoint'),
                ),
                default => new LogSmsGateway(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // A router polls once a minute. The limit is per router token, not per IP, because
        // many routers can sit behind one carrier NAT address.
        RateLimiter::for('router-agent', fn (Request $request) => Limit::perMinute(30)->by('agent:' . $request->route('token')));

        // New accounts: a person signs up once. Needs TRUSTED_PROXIES when behind a proxy.
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by('register:' . $request->ip()));

        // Guessing voucher codes one after another. Real customers redeem one code, maybe a few tries.
        RateLimiter::for('voucher', fn (Request $request) => Limit::perMinute(30)->by('voucher:' . $request->ip()));

        // The portal asks every couple of seconds whether the router has created the customer's user.
        RateLimiter::for('access', fn (Request $request) => Limit::perMinute(90)->by('access:' . (string) $request->query('ref')));

        // Setup downloads for a router. Tokens are long and random, this only slows guessing.
        RateLimiter::for('provision', fn (Request $request) => Limit::perMinute(60)->by('provision:' . $request->ip()));

        // Changing your own email or password asks for the current one. Someone who found an
        // unlocked screen should not be able to sit and guess it.
        RateLimiter::for('profile', fn (Request $request) => Limit::perMinute(6)->by('profile:' . ($request->user('tenant')?->id ?? $request->ip())));
    }
}
