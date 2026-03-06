<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class SendPhotoToTelegram extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:send-photo
                            {--no-delay : Skip the random delay}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send one unsent photo to Telegram channel with random delay (1-3 minutes)';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram): int
    {
        // Increase memory limit for large file uploads
        ini_set('memory_limit', '256M');

        $channelId = config('services.telegram.channel_id');

        if (!$channelId) {
            $this->error('Telegram channel ID not configured. Please set TELEGRAM_CHANNEL_ID in .env');
            return self::FAILURE;
        }

        // Test connection first
        $this->info('Testing Telegram bot connection...');
        $test = $telegram->testConnection();

        if (!$test['success']) {
            $this->error('Failed to connect to Telegram: ' . $test['error']);
            return self::FAILURE;
        }

        $this->info('Connected to Telegram bot: ' . $test['bot']->username);

        // Random delay between 1-3 minutes (unless --no-delay flag is set)
        if (!$this->option('no-delay')) {
            $delaySeconds = rand(60, 180); // 60-180 seconds = 1-3 minutes
            $delayMinutes = round($delaySeconds / 60, 1);
            $this->info("Waiting {$delayMinutes} minutes before sending...");
            sleep($delaySeconds);
        }

        // Find one unsent photo
        $content = Content::query()
            ->where('type', 'photo')
            ->whereNull('sent_at')
            ->where('status', 1) // 1 = active
            ->orderBy('id')
            ->first();

        if (!$content) {
            $this->info('No unsent photos found.');
            return self::SUCCESS;
        }

        $this->info("Sending: {$content->title} (photo)");

        try {
            $success = $telegram->sendContent($content, $channelId);

            if ($success) {
                $content->update([
                    'sent_at' => now(),
                    'send_attempts' => $content->send_attempts + 1,
                    'send_error' => null,
                ]);

                $this->info('✓ Sent successfully');
                return self::SUCCESS;
            } else {
                $content->increment('send_attempts');
                $this->error('✗ Failed to send (no exception thrown)');
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            $content->update([
                'send_attempts' => $content->send_attempts + 1,
                'send_error' => $errorMessage,
            ]);

            $this->error("✗ Failed: {$errorMessage}");
            return self::FAILURE;
        }
    }
}

