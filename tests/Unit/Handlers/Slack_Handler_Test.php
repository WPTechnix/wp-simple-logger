<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit\Handlers;

use Brain\Monkey\Functions;
use Psr\Log\LogLevel;
use WPTechnix\WP_Simple_Logger\Handlers\Slack_Handler;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Slack_Handler_Test extends TestCase
{
    public function test_posts_one_colored_attachment_per_entry(): void
    {
        $captured = [];
        Functions\expect('wp_remote_request')
            ->once()
            ->andReturnUsing(function ($url, $args) use (&$captured): array {
                $captured = ['url' => $url, 'args' => $args];
                return [];
            });

        $handler = new Slack_Handler('https://hooks.slack.com/services/T/B/X', LogLevel::DEBUG);
        $handler->handle(new Log_Entry('payments', 400, 'boom', '2024-01-15 10:30:00', ['order' => 5]));
        $handler->handle(new Log_Entry('api', 200, 'noise', '2024-01-15 10:31:00'));
        $handler->flush();

        $this->assertSame('https://hooks.slack.com/services/T/B/X', $captured['url']);

        $body = json_decode((string) $captured['args']['body'], true);
        $this->assertCount(2, $body['attachments']);

        $first = $body['attachments'][0];
        $this->assertSame('ERROR', $first['title']);
        $this->assertSame('#FD7E14', $first['color']);
        $this->assertStringContainsString('boom', $first['text']);
        $this->assertStringContainsString('order', $first['text']);
        $this->assertSame('WP Simple Logger', $body['username']);
    }

    public function test_transport_defaults_and_content_type(): void
    {
        $captured = [];
        Functions\expect('wp_remote_request')
            ->once()
            ->andReturnUsing(function ($url, $args) use (&$captured): array {
                $captured = $args;
                return [];
            });

        $handler = new Slack_Handler('https://hooks.slack.com/x', LogLevel::DEBUG);
        $handler->handle(new Log_Entry('ch', 500, 'x', '2024-01-15 10:30:00'));
        $handler->flush();

        $this->assertSame('application/json', $captured['headers']['Content-Type']);
        $this->assertFalse($captured['blocking']);
    }

    public function test_channel_and_icon_overrides_are_included(): void
    {
        $body = [];
        Functions\expect('wp_remote_request')
            ->once()
            ->andReturnUsing(function ($url, $args) use (&$body): array {
                $body = json_decode((string) $args['body'], true);
                return [];
            });

        $handler = new Slack_Handler('https://hooks.slack.com/x', LogLevel::DEBUG);
        $handler->set_channel('#alerts')->set_icon_emoji(':rotating_light:')->set_username('Alertbot');
        $handler->handle(new Log_Entry('ch', 500, 'x', '2024-01-15 10:30:00'));
        $handler->flush();

        $this->assertSame('#alerts', $body['channel']);
        $this->assertSame(':rotating_light:', $body['icon_emoji']);
        $this->assertSame('Alertbot', $body['username']);
    }

    public function test_defaults_to_error_min_level(): void
    {
        $handler = new Slack_Handler('https://hooks.slack.com/x');

        $this->assertFalse($handler->should_handle(new Log_Entry('ch', 200, 'info', '2024-01-15 10:30:00')));
        $this->assertTrue($handler->should_handle(new Log_Entry('ch', 400, 'err', '2024-01-15 10:30:00')));
    }

    public function test_no_request_when_buffer_empty(): void
    {
        Functions\expect('wp_remote_request')->never();

        $handler = new Slack_Handler('https://hooks.slack.com/x');
        $handler->flush();

        $this->expectNotToPerformAssertions();
    }

    public function test_long_context_is_truncated_in_attachment_text(): void
    {
        $captured = [];
        Functions\expect('wp_remote_request')
            ->once()
            ->andReturnUsing(function ($url, $args) use (&$captured): array {
                $captured = $args;
                return [];
            });

        $large_context = ['payload' => str_repeat('a', 2000)];

        $handler = new Slack_Handler('https://hooks.slack.com/x', LogLevel::DEBUG);
        $handler->handle(new Log_Entry('ch', 500, 'boom', '2024-01-15 10:30:00', $large_context));
        $handler->flush();

        $body = json_decode((string) $captured['body'], true);
        $text = $body['attachments'][0]['text'];

        $this->assertStringContainsString('boom', $text);
        $this->assertStringContainsString('... (truncated)', $text);
        $this->assertStringNotContainsString(str_repeat('a', 2000), $text);
    }
}
