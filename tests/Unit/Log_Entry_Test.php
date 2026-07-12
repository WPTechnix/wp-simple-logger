<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit;

use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Log_Entry_Test extends TestCase
{
    private Log_Entry $entry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entry = new Log_Entry(
            'test-channel',
            200,
            'Test message {foo}',
            '2024-01-15 10:30:00',
            ['foo' => 'bar'],
            42
        );
    }

    public function test_get_id(): void
    {
        $this->assertSame(42, $this->entry->get_id());
    }

    public function test_get_id_returns_null_when_not_set(): void
    {
        $entry = new Log_Entry('ch', 100, 'msg', '2024-01-01 00:00:00');
        $this->assertNull($entry->get_id());
    }

    public function test_get_channel(): void
    {
        $this->assertSame('test-channel', $this->entry->get_channel());
    }

    public function test_get_level_priority(): void
    {
        $this->assertSame(200, $this->entry->get_level_priority());
    }

    public function test_get_level_name(): void
    {
        $this->assertSame('info', $this->entry->get_level_name());
    }

    public function test_get_message(): void
    {
        $this->assertSame('Test message {foo}', $this->entry->get_message());
    }

    public function test_get_context(): void
    {
        $this->assertSame(['foo' => 'bar'], $this->entry->get_context());
    }

    public function test_get_context_returns_null_when_not_set(): void
    {
        $entry = new Log_Entry('ch', 100, 'msg', '2024-01-01 00:00:00');
        $this->assertNull($entry->get_context());
    }

    public function test_get_date_time(): void
    {
        $this->assertSame('2024-01-15 10:30:00', $this->entry->get_date_time());
    }
}
