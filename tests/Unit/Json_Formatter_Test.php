<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit;

use WPTechnix\WP_Simple_Logger\Formatters\Json_Formatter;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Json_Formatter_Test extends TestCase
{
    public function test_default_format(): void
    {
        $entry = new Log_Entry('test', 500, 'critical!', '2024-01-15 10:30:00', ['err' => 1]);
        $formatter = new Json_Formatter();
        $output = $formatter->format($entry);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame('2024-01-15 10:30:00', $decoded['datetime']);
        $this->assertSame('test', $decoded['channel']);
        $this->assertSame(500, $decoded['level']);
        $this->assertSame('critical', $decoded['levelName']);
        $this->assertSame('critical!', $decoded['message']);
        $this->assertSame(['err' => 1], $decoded['context']);
    }

    public function test_custom_keys(): void
    {
        $entry = new Log_Entry('ch', 200, 'msg', '2024-01-15 10:30:00');
        $formatter = new Json_Formatter(['message', 'channel']);
        $output = $formatter->format($entry);

        $decoded = json_decode($output, true);
        $this->assertArrayHasKey('message', $decoded);
        $this->assertArrayHasKey('channel', $decoded);
        $this->assertArrayNotHasKey('datetime', $decoded);
    }

    public function test_context_is_empty_object_when_null(): void
    {
        $entry = new Log_Entry('ch', 200, 'msg', '2024-01-15 10:30:00');
        $formatter = new Json_Formatter();
        $output = $formatter->format($entry);

        $decoded = json_decode($output, false);
        $this->assertInstanceOf(\stdClass::class, $decoded->context);
    }
}
