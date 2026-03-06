<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class SendContentToTelegram extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:send-content
                            {--limit=10 : Number of contents to send}
                            {--type= : Filter by content type (photo/video)}
                            {--category= : Filter by category ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send unsent content to Telegram channel';

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

        // Build query for unsent content
        $query = Content::query()
            ->whereNull('sent_at')
            ->where('status', true)
            ->with('category');

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        if ($categoryId = $this->option('category')) {
            $query->where('category_id', $categoryId);
        }

        $limit = (int) $this->option('limit');
        $contents = $query->limit($limit)->get();

        if ($contents->isEmpty()) {
            $this->info('No unsent content found.');
            return self::SUCCESS;
        }

        $this->info("Found {$contents->count()} content(s) to send.");

        $successCount = 0;
        $failureCount = 0;

        foreach ($contents as $content) {
            $this->info("Sending: {$content->title} ({$content->type})");

            try {
                $success = $telegram->sendContent($content, $channelId);

                if ($success) {
                    $content->update([
                        'sent_at' => now(),
                        'send_attempts' => $content->send_attempts + 1,
                        'send_error' => null,
                    ]);

                    $this->info("✓ Sent successfully");
                    $successCount++;

                    // Rate limiting: wait 1 second between messages
                    sleep(1);
                } else {
                    throw new \Exception('Failed to send content');
                }
            } catch (\Exception $e) {
                $content->update([
                    'send_attempts' => $content->send_attempts + 1,
                    'send_error' => $e->getMessage(),
                ]);

                $this->error("✗ Failed: {$e->getMessage()}");
                $failureCount++;
            }
        }

        $this->newLine();
        $this->info("Summary: {$successCount} sent, {$failureCount} failed");

        return self::SUCCESS;
    }
}
