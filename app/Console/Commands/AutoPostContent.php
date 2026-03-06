<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AutoPostContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:auto-post
                            {--limit=5 : Number of contents to send}
                            {--skip-scrape : Skip scraping and only send existing content}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape new content and automatically post to Telegram';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting auto-post workflow...');
        $this->newLine();

        // Step 1: Scrape new content (unless skipped)
        if (!$this->option('skip-scrape')) {
            $this->info('Step 1: Scraping new content from maomanke.com');
            $scrapeResult = $this->call('scrape:maomanke');

            if ($scrapeResult !== self::SUCCESS) {
                $this->error('Scraping failed. Aborting.');
                return self::FAILURE;
            }

            $this->newLine();
        } else {
            $this->info('Skipping scrape step as requested.');
            $this->newLine();
        }

        // Step 2: Send content to Telegram
        $this->info('Step 2: Sending content to Telegram');
        $sendResult = $this->call('telegram:send-content', [
            '--limit' => $this->option('limit'),
        ]);

        if ($sendResult !== self::SUCCESS) {
            $this->error('Sending failed.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Auto-post workflow completed successfully!');

        return self::SUCCESS;
    }
}
