<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use SergiX44\Nutgram\Configuration;
use SergiX44\Nutgram\Nutgram;

class TelegramServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Nutgram::class, function ($app) {
            $token = config('services.telegram.bot_token');

            if (!$token) {
                throw new \RuntimeException('Telegram bot token not configured. Please set TELEGRAM_BOT_TOKEN in .env');
            }

            return new Nutgram($token, new Configuration(
                clientTimeout: 120 // 120 seconds for large file uploads
            ));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
