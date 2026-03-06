<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Content;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ScrapeMaomanke extends Command
{
    protected $signature = 'scrape:maomanke
                            {--pages=10 : Number of pages to scrape}
                            {--size=50 : Number of items per page}
                            {--type=* : Content types to scrape (video, photo)}
                            {--loop : Enable continuous loop mode}
                            {--delay=2 : Delay in seconds between requests}';

    protected $description = 'Scrape content data from maomanke.com API with pagination support';

    private const API_BASE_URL = 'https://server.maomanke.com/api';
    private const RETRY_TIMES = 3;
    private const TIMEOUT = 30;

    public function handle(): int
    {
        $this->info('Starting Maomanke data scraping...');
        $startTime = microtime(true);

        try {
            // Always scrape categories first
            $this->scrapeCategories();

            // Get scraping parameters
            $pages = (int) $this->option('pages');
            $pageSize = (int) $this->option('size');
            $types = $this->option('type');
            $loop = $this->option('loop');
            $delay = (int) $this->option('delay');

            // Default to both types if none specified
            if (empty($types)) {
                $types = ['video', 'photo'];
            }

            $this->newLine();
            $this->info("Configuration:");
            $this->info("  Pages: {$pages}");
            $this->info("  Page Size: {$pageSize}");
            $this->info("  Types: " . implode(', ', $types));
            $this->info("  Loop Mode: " . ($loop ? 'Enabled' : 'Disabled'));
            $this->info("  Delay: {$delay}s");
            $this->newLine();

            $iteration = 1;
            do {
                if ($loop) {
                    $this->info("=== Iteration #{$iteration} ===");
                }

                $totalNew = 0;
                $totalUpdated = 0;

                // Scrape content with pagination
                for ($page = 1; $page <= $pages; $page++) {
                    $this->info("Scraping page {$page}/{$pages}...");

                    $result = $this->scrapeContentsPage($types, $pageSize, $page);

                    $totalNew += $result['new'];
                    $totalUpdated += $result['updated'];

                    // Delay between requests to avoid rate limiting
                    if ($page < $pages) {
                        sleep($delay);
                    }
                }

                $this->newLine();
                $this->info("Total: {$totalNew} new, {$totalUpdated} updated");

                if ($loop) {
                    $this->newLine();
                    $this->info("Waiting {$delay} seconds before next iteration...");
                    sleep($delay);
                    $iteration++;
                }
            } while ($loop && !$this->shouldStop());

            $duration = round(microtime(true) - $startTime, 2);
            $this->newLine();
            $this->info("✓ Scraping completed successfully in {$duration} seconds");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Scraping failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function shouldStop(): bool
    {
        // Check if user pressed Ctrl+C or other stop signals
        return false;
    }

    private function scrapeCategories(): void
    {
        $this->info('Fetching categories...');

        $response = $this->makeRequest('/contentCategory');

        if (!isset($response['data'])) {
            throw new \Exception('Invalid category response format');
        }

        $categories = $response['data'];
        $this->info('Found ' . count($categories) . ' categories');

        $newCount = 0;
        $updatedCount = 0;

        $bar = $this->output->createProgressBar(count($categories));
        $bar->start();

        foreach ($categories as $categoryData) {
            $wasRecentlyCreated = $this->saveCategory($categoryData);
            $wasRecentlyCreated ? $newCount++ : $updatedCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Categories: {$newCount} new, {$updatedCount} updated");
    }

    private function scrapeContentsPage(array $types, int $pageSize, int $page): array
    {
        $response = $this->makeRequest('/contentSuggest', [
            'type' => $types,
            'size' => $pageSize,
        ]);

        if (!isset($response['data'])) {
            throw new \Exception('Invalid content response format');
        }

        $contents = $response['data'];
        $count = count($contents);

        if ($count === 0) {
            $this->warn("  No content found on page {$page}");
            return ['new' => 0, 'updated' => 0];
        }

        $this->info("  Found {$count} contents");

        $newCount = 0;
        $updatedCount = 0;

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($contents as $contentData) {
            $wasRecentlyCreated = $this->saveContent($contentData);
            $wasRecentlyCreated ? $newCount++ : $updatedCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Page {$page}: {$newCount} new, {$updatedCount} updated");

        return ['new' => $newCount, 'updated' => $updatedCount];
    }

    private function scrapeContents(): void
    {
        $this->info('Fetching contents...');

        $response = $this->makeRequest('/contentSuggest');

        if (!isset($response['data'])) {
            throw new \Exception('Invalid content response format');
        }

        $contents = $response['data'];
        $this->info('Found ' . count($contents) . ' contents');

        $newCount = 0;
        $updatedCount = 0;

        $bar = $this->output->createProgressBar(count($contents));
        $bar->start();

        foreach ($contents as $contentData) {
            $wasRecentlyCreated = $this->saveContent($contentData);
            $wasRecentlyCreated ? $newCount++ : $updatedCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Contents: {$newCount} new, {$updatedCount} updated");
    }

    private function saveCategory(array $data): bool
    {
        $category = Category::updateOrCreate(
            ['external_id' => $data['_id']],
            [
                'type' => $data['type'],
                'key' => $data['key'],
                'title' => $data['title'],
                'icon' => $data['icon'] ?? null,
                'order' => $data['order'] ?? 0,
                'status' => $data['status'] == 1,
                'external_created_at' => $data['createdAt'] ?? null,
                'external_updated_at' => $data['updatedAt'] ?? null,
            ]
        );

        return $category->wasRecentlyCreated;
    }

    private function saveContent(array $data): bool
    {
        $category = Category::where('external_id', $data['category'])->first();

        if (!$category) {
            $this->warn("Category not found for content: {$data['_id']}");
            return false;
        }

        $content = Content::updateOrCreate(
            ['external_id' => $data['_id']],
            [
                'category_id' => $category->id,
                'type' => $data['type'],
                'title' => $data['title'],
                'cover' => $data['cover'],
                'content' => $data['content'],
                'price' => $data['price'] ?? 0,
                'views' => $data['views'] ?? 0,
                'collects' => $data['collects'] ?? 0,
                'shares' => $data['shares'] ?? 0,
                'comments' => $data['comments'] ?? 0,
                'duration' => $data['duration'] ?? null,
                'status' => $data['status'] == 1,
                'member_data' => $data['member'] ?? null,
                'external_created_at' => $data['createdAt'] ?? null,
                'external_updated_at' => $data['updatedAt'] ?? null,
            ]
        );

        return $content->wasRecentlyCreated;
    }

    private function makeRequest(string $endpoint, array $payload = []): array
    {
        $url = self::API_BASE_URL . $endpoint;

        $request = Http::timeout(self::TIMEOUT)
            ->retry(self::RETRY_TIMES, 1000)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'Accept' => 'application/json',
            ]);

        // Use POST with payload if provided, otherwise just POST
        if (!empty($payload)) {
            $response = $request->post($url, $payload);
        } else {
            $response = $request->post($url);
        }

        if (!$response->successful()) {
            throw new \Exception("API request failed: {$url} - Status: {$response->status()}");
        }

        return $response->json();
    }
}
