<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit\Handlers;

use Brain\Monkey\Functions;
use Psr\Log\LogLevel;
use WPTechnix\WP_Simple_Logger\Handlers\Webhook_Handler;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Webhook_Handler_Test extends TestCase
{
    public function test_posts_structured_logs_array(): void
    {
        $captured = [];
        Functions\expect('wp_remote_request')
            ->once()
            ->andReturnUsing(function ($url, $args) use (&$captured): array {
                $captured = ['url' => $url, 'args' => $args];
                return [];
            });

        $handler = new Webhook_Handler('https://ingest.example.com/logs', LogLevel::DEBUG);
        $handler->handle(new Log_Entry('api', 300, 'hi', '2024-01-15 10:30:00', ['k' => 'v']));
        $handler->handle(new Log_Entry('api', 400, 'bye', '2024-01-15 10:31:00'));
        $handler->flush();

        $this->assertSame('https://ingest.example.com/logs', $captured['url']);

        $body = json_decode((string) $captured['args']['body'], true);
        $this->assertCount(2, $body['logs']);
        $this->assertSame('api', $body['logs'][0]['channel']);
        $this->assertSame('warning', $body['logs'][0]['level_name']);
        $this->assertSame('hi', $body['logs'][0]['message']);
        $this->assertSame(['k' => 'v'], $body['logs'][0]['context']);
        $this->assertNull($body['logs'][1]['context']);
    }

    public function test_transport_args_are_applied(): void
    {
        $captured = [];
        Functions\expect('wp_remote_request')
            ->once()
            ->andReturnUsing(function ($url, $args) use (&$captured): array {
                $captured = $args;
                return [];
            });

        $handler = new Webhook_Handler('https://ingest.example.com/logs', LogLevel::DEBUG);
        $handler->set_timeout(2.5)
            ->set_blocking(true)
            ->set_request_args(['sslverify' => false]);
        $handler->handle(new Log_Entry('ch', 200, 'm', '2024-01-15 10:30:00'));
        $handler->flush();

        $this->assertSame(2.5, $captured['timeout']);
        $this->assertTrue($captured['blocking']);
        // The escape hatch fills in extra args without clobbering managed keys.
        $this->assertFalse($captured['sslverify']);
    }

    public function test_defaults_to_post_and_json_content_type(): void
    {
        $captured = [];
        Functions\expect('wp_remote_request')
            ->once()
            ->andReturnUsing(function ($url, $args) use (&$captured): array {
                $captured = $args;
                return [];
            });

        $handler = new Webhook_Handler('https://ingest.example.com/logs', LogLevel::DEBUG);
        $handler->handle(new Log_Entry('ch', 200, 'm', '2024-01-15 10:30:00'));
        $handler->flush();

        $this->assertSame('POST', $captured['method']);
        $this->assertSame('application/json', $captured['headers']['Content-Type']);
    }

    public function test_set_method_and_add_header(): void
    {
        $captured = [];
        Functions\expect('wp_remote_request')
            ->once()
            ->andReturnUsing(function ($url, $args) use (&$captured): array {
                $captured = $args;
                return [];
            });

        $handler = new Webhook_Handler('https://ingest.example.com/logs', LogLevel::DEBUG);
        $handler->set_method('put')->add_header('Authorization', 'Bearer token');
        $handler->handle(new Log_Entry('ch', 200, 'm', '2024-01-15 10:30:00'));
        $handler->flush();

        $this->assertSame('PUT', $captured['method']);
        $this->assertSame('Bearer token', $captured['headers']['Authorization']);
        // add_header keeps the default JSON content type.
        $this->assertSame('application/json', $captured['headers']['Content-Type']);
    }

    public function test_no_request_when_buffer_empty(): void
    {
        Functions\expect('wp_remote_request')->never();

        $handler = new Webhook_Handler('https://ingest.example.com/logs');
        $handler->flush();

        $this->expectNotToPerformAssertions();
    }
}
