<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit;

use WPTechnix\WP_Simple_Logger\Formatters\Line_Formatter;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Line_Formatter_Test extends TestCase
{
    public function test_default_format_with_context(): void
    {
        $entry = new Log_Entry('test', 200, 'hello', '2024-01-15 10:30:00', ['key' => 'val']);
        $formatter = new Line_Formatter();
        $output = $formatter->format($entry);

        $this->assertStringContainsString('[2024-01-15', $output);
        $this->assertStringContainsString('test.INFO:', $output);
        $this->assertStringContainsString('hello', $output);
        $this->assertStringContainsString('{"key":"val"}', $output);
    }

    public function test_default_format_without_context(): void
    {
        $entry = new Log_Entry('test', 400, 'error msg', '2024-01-15 10:30:00');
        $formatter = new Line_Formatter();
        $output = $formatter->format($entry);

        $this->assertStringContainsString('[2024-01-15', $output);
        $this->assertStringContainsString('test.ERROR:', $output);
        $this->assertStringContainsString('error msg', $output);
        $this->assertStringNotContainsString('{}', $output);
        // The %context% token (and its preceding space) must be stripped cleanly,
        // leaving no trailing space before the newline.
        $this->assertStringNotContainsString('error msg ', $output);
        $this->assertStringEndsWith('error msg' . PHP_EOL, $output);
    }

    public function test_custom_format(): void
    {
        $entry = new Log_Entry('ch', 300, 'warn', '2024-01-15 10:30:00');
        $formatter = new Line_Formatter('[%level_name%] %message%' . PHP_EOL);
        $output = $formatter->format($entry);

        $this->assertSame('[WARNING] warn' . PHP_EOL, $output);
    }

    public function test_ignore_empty_context_false(): void
    {
        $entry = new Log_Entry('ch', 200, 'msg', '2024-01-15 10:30:00');
        $formatter = new Line_Formatter(null, false);
        $output = $formatter->format($entry);

        $this->assertStringContainsString('msg ', $output);
        $this->assertStringNotContainsString('%context%', $output);
    }
}
