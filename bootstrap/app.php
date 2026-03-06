<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Scrape new content every 30 minutes
        $schedule->command('scrape:maomanke')->everyThirtyMinutes();

        // Send content to Telegram every hour
        $schedule->command('telegram:send-content --limit=5')->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
