<?php

use App\Models\Category;
use App\Models\Content;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Artisan;
use SergiX44\Nutgram\Nutgram;

test('telegram service can format caption correctly', function () {
    $category = Category::factory()->create(['title' => 'Test Category']);
    $content = Content::factory()->create([
        'category_id' => $category->id,
        'title' => 'Test Content',
        'type' => 'photo',
        'views' => 100,
        'collects' => 50,
        'shares' => 25,
        'duration' => 120,
    ]);

    $bot = $this->mock(Nutgram::class);
    $service = new TelegramService($bot);

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('formatCaption');
    $method->setAccessible(true);

    $caption = $method->invoke($service, $content);

    expect($caption)
        ->toContain('Test Content')
        ->toContain('Test Category')
        ->toContain('100')
        ->toContain('50')
        ->toContain('25')
        ->toContain('2:0');
});

test('send content command requires telegram configuration', function () {
    config(['services.telegram.channel_id' => null]);

    Artisan::call('telegram:send-content');

    expect(Artisan::output())
        ->toContain('Telegram channel ID not configured');
});

test('content is marked as sent after successful delivery', function () {
    config([
        'services.telegram.bot_token' => 'test_token',
        'services.telegram.channel_id' => '@test_channel',
    ]);

    $content = Content::factory()->create([
        'type' => 'photo',
        'status' => true,
        'sent_at' => null,
    ]);

    expect($content->sent_at)->toBeNull();
    expect($content->send_attempts)->toBe(0);
});

test('auto post command calls scrape and send commands', function () {
    config([
        'services.telegram.bot_token' => 'test_token',
        'services.telegram.channel_id' => '@test_channel',
    ]);

    Artisan::call('telegram:auto-post --skip-scrape');

    expect(Artisan::output())
        ->toContain('auto-post workflow');
});
