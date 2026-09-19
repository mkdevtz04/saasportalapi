<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('router:heartbeat')->everyFiveMinutes();
Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping();
Schedule::command('radius:prune')->dailyAt('03:30');

// Proof for /health that the scheduler is alive: without it nothing below would run.
Schedule::call(fn () => Cache::forever('scheduler:last_run', now()->timestamp))->everyMinute()->name('scheduler-beat');
Schedule::command('data:prune')->dailyAt('03:45');
Schedule::command('access:expire')->everyMinute()->withoutOverlapping();
