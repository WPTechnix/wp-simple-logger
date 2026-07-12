<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit;

use Psr\Log\LogLevel;
use WPTechnix\WP_Simple_Logger\Handlers\Null_Handler;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Null_Handler_Test extends TestCase
{
    public function test_handle_returns_true(): void
    {
        $handler = new Null_Handler();
        $entry = new Log_Entry('ch', 200, 'msg', '2024-01-01 00:00:00');

        $this->assertTrue($handler->handle($entry));
    }

    public function test_flush_is_a_noop(): void
    {
        $handler = new Null_Handler();
        $handler->handle(new Log_Entry('ch', 200, 'msg', '2024-01-01 00:00:00'));
        $handler->flush();

        $this->expectNotToPerformAssertions();
    }

    public function test_respects_min_level(): void
    {
        $handler = new Null_Handler(LogLevel::ERROR);

        $this->assertFalse($handler->should_handle(new Log_Entry('ch', 200, 'info', '2024-01-01 00:00:00')));
        $this->assertTrue($handler->should_handle(new Log_Entry('ch', 400, 'err', '2024-01-01 00:00:00')));
    }
}
