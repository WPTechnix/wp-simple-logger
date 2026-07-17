<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit\Formatters;

use WPTechnix\WP_Simple_Logger\Formatters\Html_Formatter;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Html_Formatter_Test extends TestCase
{
    private Html_Formatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new Html_Formatter();
    }

    public function test_format_contains_level_title(): void
    {
        $entry = new Log_Entry('test', 400, 'error occurred', '2024-01-15 10:30:00');
        $output = $this->formatter->format($entry);

        $this->assertStringContainsString('ERROR', $output);
    }

    public function test_format_contains_message(): void
    {
        $entry = new Log_Entry('test', 200, 'info msg', '2024-01-15 10:30:00');
        $output = $this->formatter->format($entry);

        $this->assertStringContainsString('info msg', $output);
    }

    public function test_format_contains_context_when_provided(): void
    {
        $entry = new Log_Entry('test', 200, 'msg', '2024-01-15 10:30:00', ['foo' => 'bar']);
        $output = $this->formatter->format($entry);

        $this->assertStringContainsString('Context', $output);
        $this->assertStringContainsString('foo', $output);
        $this->assertStringContainsString('bar', $output);
    }

    public function test_format_omits_context_when_null(): void
    {
        $entry = new Log_Entry('test', 200, 'msg', '2024-01-15 10:30:00');
        $output = $this->formatter->format($entry);

        $this->assertStringNotContainsString('Context', $output);
    }

    public function test_format_returns_table_wrapper(): void
    {
        $entry = new Log_Entry('test', 200, 'msg', '2024-01-15 10:30:00');
        $output = $this->formatter->format($entry);

        $this->assertStringContainsString('<table', $output);
        $this->assertStringContainsString('</table>', $output);
    }
}
