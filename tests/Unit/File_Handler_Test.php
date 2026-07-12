<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit;

use Psr\Log\LogLevel;
use WPTechnix\WP_Simple_Logger\Handlers\File_Handler;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class File_Handler_Test extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/wpsl_file_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_writes_formatted_line_on_flush(): void
    {
        $path = $this->dir . '/app.log';
        $handler = new File_Handler($path);

        $handler->handle(new Log_Entry('ch', 200, 'hello world', '2024-01-15 10:30:00', ['a' => 1]));
        $handler->flush();

        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('ch.INFO: hello world', $contents);
        $this->assertStringContainsString('{"a":1}', $contents);
    }

    public function test_nothing_is_written_before_flush_without_buffer_limit(): void
    {
        $path = $this->dir . '/app.log';
        $handler = new File_Handler($path);

        $handler->handle(new Log_Entry('ch', 200, 'buffered', '2024-01-15 10:30:00'));

        $this->assertFileDoesNotExist($path);
    }

    public function test_buffer_limit_triggers_flush(): void
    {
        $path = $this->dir . '/buffer.log';
        $handler = new File_Handler($path, LogLevel::DEBUG, 2);

        $handler->handle(new Log_Entry('ch', 200, 'one', '2024-01-15 10:30:00'));
        $this->assertFileDoesNotExist($path);

        $handler->handle(new Log_Entry('ch', 200, 'two', '2024-01-15 10:30:00'));
        $this->assertFileExists($path);

        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('one', $contents);
        $this->assertStringContainsString('two', $contents);
    }

    public function test_creates_missing_directory(): void
    {
        $path = $this->dir . '/nested/deep/app.log';
        $handler = new File_Handler($path);

        $handler->handle(new Log_Entry('ch', 200, 'msg', '2024-01-15 10:30:00'));
        $handler->flush();

        $this->assertFileExists($path);
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items === false ? [] : $items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
