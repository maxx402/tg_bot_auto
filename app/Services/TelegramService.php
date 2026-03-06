<?php

namespace App\Services;

use App\Models\Content;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Input\InputMediaPhoto;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;

class TelegramService
{
    public function __construct(
        protected Nutgram $bot
    ) {
    }

    /**
     * Send content to Telegram channel
     */
    public function sendContent(Content $content, string $channelId): bool
    {
        if ($content->type === 'photo') {
            return $this->sendPhotoAlbum($content, $channelId);
        }

        if ($content->type === 'video') {
            return $this->sendVideo($content, $channelId);
        }

        Log::warning("Unknown content type: {$content->type}", ['content_id' => $content->id]);
        throw new \InvalidArgumentException("Unknown content type: {$content->type}");
    }

    /**
     * Send photo album (media group) to Telegram
     */
    protected function sendPhotoAlbum(Content $content, string $channelId): bool
    {
        $urls = $content->contentUrls;

        if (empty($urls)) {
            Log::warning('No photo URLs found', ['content_id' => $content->id]);
            return false;
        }

        // Limit to 9 photos per album (Telegram allows 10, but we use 9 for safety)
        $urls = array_slice($urls, 0, 9);

        $caption = $this->formatCaption($content);

        try {
            // For single photo
            if (count($urls) === 1) {
                $this->bot->sendPhoto(
                    photo: $urls[0],
                    chat_id: $channelId,
                    caption: $caption,
                    parse_mode: 'HTML'
                );
                return true;
            }

            // For multiple photos (media group) - send URLs directly
            $media = [];
            foreach ($urls as $index => $url) {
                $media[] = InputMediaPhoto::make(
                    media: $url,
                    caption: $index === 0 ? $caption : null,
                    parse_mode: $index === 0 ? ParseMode::HTML : null
                );
            }

            $this->bot->sendMediaGroup(
                media: $media,
                chat_id: $channelId
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send photos to Telegram', [
                'content_id' => $content->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send video to Telegram
     */
    protected function sendVideo(Content $content, string $channelId): bool
    {
        $urls = $content->contentUrls;

        if (empty($urls)) {
            Log::warning('No video URL found', ['content_id' => $content->id]);
            return false;
        }

        $videoUrl = $urls[0];
        $caption = $this->formatCaption($content);

        // Download video to local file
        $localPath = $this->downloadVideo($videoUrl, $content->id);

        if (!$localPath) {
            Log::error('Failed to download video', ['content_id' => $content->id, 'url' => $videoUrl]);
            return false;
        }

        try {
            // Check file size (Telegram bot API limit is 50 MB)
            $fileSize = filesize($localPath);
            $maxSize = 50 * 1024 * 1024; // 50 MB in bytes

            if ($fileSize > $maxSize) {
                Log::warning('Video file too large for Telegram', [
                    'content_id' => $content->id,
                    'size' => $fileSize,
                    'max_size' => $maxSize,
                    'size_mb' => round($fileSize / 1024 / 1024, 2),
                ]);
                throw new \Exception('Video file exceeds Telegram bot API limit of 50 MB (' . round($fileSize / 1024 / 1024, 2) . ' MB)');
            }

            // Send video from local file
            $this->bot->sendVideo(
                video: InputFile::make($localPath),
                chat_id: $channelId,
                caption: $caption,
                parse_mode: 'HTML',
                supports_streaming: true
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send video to Telegram', [
                'content_id' => $content->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            // Always delete the local file after sending (success or failure)
            $this->deleteLocalFile($localPath);
        }
    }

    /**
     * Download video to local temporary file
     */
    protected function downloadVideo(string $url, int $contentId): ?string
    {
        try {
            // Create temp directory if not exists
            $tempDir = storage_path('app/temp/videos');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Generate unique filename
            $filename = $contentId . '_' . time() . '.mp4';
            $localPath = $tempDir . '/' . $filename;

            // Download video with timeout
            $response = \Illuminate\Support\Facades\Http::timeout(120)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning('Failed to download video', [
                    'content_id' => $contentId,
                    'status' => $response->status(),
                ]);
                return null;
            }

            // Save to local file
            file_put_contents($localPath, $response->body());

            Log::info('Video downloaded successfully', [
                'content_id' => $contentId,
                'size' => filesize($localPath),
                'path' => $localPath,
            ]);

            return $localPath;
        } catch (\Exception $e) {
            Log::error('Exception while downloading video', [
                'content_id' => $contentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Download photo to local temporary file
     */
    protected function downloadPhoto(string $url, int $contentId, int $index = 0): ?string
    {
        try {
            // Create temp directory if not exists
            $tempDir = storage_path('app/temp/photos');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Generate unique filename
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = $contentId . '_' . $index . '_' . time() . '.' . $extension;
            $localPath = $tempDir . '/' . $filename;

            // Download photo with timeout
            $response = \Illuminate\Support\Facades\Http::timeout(60)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning('Failed to download photo', [
                    'content_id' => $contentId,
                    'index' => $index,
                    'status' => $response->status(),
                ]);
                return null;
            }

            // Save to local file
            file_put_contents($localPath, $response->body());

            Log::info('Photo downloaded successfully', [
                'content_id' => $contentId,
                'index' => $index,
                'size' => filesize($localPath),
                'path' => $localPath,
            ]);

            return $localPath;
        } catch (\Exception $e) {
            Log::error('Exception while downloading photo', [
                'content_id' => $contentId,
                'index' => $index,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Delete local file
     */
    protected function deleteLocalFile(string $path): void
    {
        try {
            if (file_exists($path)) {
                unlink($path);
                Log::info('Local file deleted', ['path' => $path]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to delete local file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Format caption for Telegram message
     */
    protected function formatCaption(Content $content): string
    {
        // For photos: Title + Tags only
        if ($content->type === 'photo') {
            return $this->formatPhotoCaption($content);
        }

        // For videos: Full format with stats
        return $this->formatVideoCaption($content);
    }

    /**
     * Format caption for photo messages (Title + Tags)
     */
    protected function formatPhotoCaption(Content $content): string
    {
        $title = $content->title;

        // Extract tags from title (format: xxx-yyy)
        $tags = $this->extractTags($title);

        $caption = "<b>{$title}</b>";

        if (!empty($tags)) {
            $caption .= "\n\n";
            $caption .= implode(' ', array_map(fn($tag) => "#{$tag}", $tags));
        }

        return $caption;
    }

    /**
     * Format caption for video messages (Full format)
     */
    protected function formatVideoCaption(Content $content): string
    {
        $caption = "<b>{$content->title}</b>\n\n";

        if ($content->category) {
            $caption .= "📁 {$content->category->title}\n";
        }

        $stats = [];
        if ($content->views > 0) {
            $stats[] = "👁 {$content->views}";
        }
        if ($content->collects > 0) {
            $stats[] = "⭐ {$content->collects}";
        }
        if ($content->shares > 0) {
            $stats[] = "🔄 {$content->shares}";
        }

        if (!empty($stats)) {
            $caption .= implode(' | ', $stats) . "\n";
        }

        if ($content->duration) {
            $minutes = floor($content->duration / 60);
            $seconds = $content->duration % 60;
            $caption .= "⏱ {$minutes}:{$seconds}\n";
        }

        return $caption;
    }

    /**
     * Extract tags from title (format: xxx-yyy becomes #xxx #yyy)
     */
    protected function extractTags(string $title): array
    {
        $tags = [];

        // Match patterns like "神印王座-圣采儿" or "斗罗大陆2-冰帝"
        // Pattern: Chinese/English characters followed by dash and more characters
        preg_match_all('/([^\s\-\(（]+)-([^\s\-\(（]+)/', $title, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            // $match[1] is the first part (e.g., "神印王座")
            // $match[2] is the second part (e.g., "圣采儿")

            // Add first part as tag
            $tag1 = trim($match[1]);
            if (!empty($tag1)) {
                $cleanTag1 = preg_replace('/[^\p{L}\p{N}]/u', '', $tag1);
                if (!empty($cleanTag1)) {
                    $tags[] = $cleanTag1;
                }
            }

            // Add second part as tag
            $tag2 = trim($match[2]);
            if (!empty($tag2)) {
                $cleanTag2 = preg_replace('/[^\p{L}\p{N}]/u', '', $tag2);
                if (!empty($cleanTag2)) {
                    $tags[] = $cleanTag2;
                }
            }
        }

        return array_unique($tags);
    }

    /**
     * Test bot connection
     */
    public function testConnection(): array
    {
        try {
            $me = $this->bot->getMe();
            return [
                'success' => true,
                'bot' => $me,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
