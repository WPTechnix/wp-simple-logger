<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit\Handlers;

use Mockery;
use Psr\Log\LogLevel;
use WPTechnix\WP_Simple_Logger\Handlers\Database_Handler;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Database_Handler_Test extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_flush_inserts_buffered_logs(): void
    {
        $wpdb = Mockery::mock(\wpdb::class);
        $wpdb->shouldReceive('prepare')->once()->andReturn('PREPARED');
        $wpdb->shouldReceive('query')->once()->with('PREPARED')->andReturn(1);
        $GLOBALS['wpdb'] = $wpdb;

        $handler = new Database_Handler('wp_logs', LogLevel::DEBUG, 0);
        $this->assertTrue($handler->handle(new Log_Entry('ch', 400, 'boom', '2024-01-15 10:30:00')));
        $handler->flush();
    }

    public function test_buffer_limit_triggers_insert(): void
    {
        $wpdb = Mockery::mock(\wpdb::class);
        $wpdb->shouldReceive('prepare')->once()->andReturn('PREPARED');
        $wpdb->shouldReceive('query')->once()->with('PREPARED')->andReturn(2);
        $GLOBALS['wpdb'] = $wpdb;

        $handler = new Database_Handler('wp_logs', LogLevel::DEBUG, 2);
        $this->assertTrue($handler->handle(new Log_Entry('ch', 400, 'one', '2024-01-15 10:30:00')));
        $this->assertTrue($handler->handle(new Log_Entry('ch', 400, 'two', '2024-01-15 10:31:00')));
    }

    public function test_invalid_table_name_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Database_Handler('bad name');
    }
}
