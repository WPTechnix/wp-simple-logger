<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit;

use Psr\Log\LogLevel;
use WPTechnix\WP_Simple_Logger\Contracts\Formatter_Interface;
use WPTechnix\WP_Simple_Logger\Handlers\Abstract_Handler;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Abstract_Handler_Test extends TestCase
{
    public function test_should_handle_accepts_all_by_default(): void
    {
        $handler = $this->createConcreteHandler();

        $entry = new Log_Entry('any', 100, 'msg', '2024-01-01 00:00:00');
        $this->assertTrue($handler->should_handle($entry));
    }

    public function test_should_handle_filters_by_min_level(): void
    {
        $handler = $this->createConcreteHandler(LogLevel::ERROR);

        $debugEntry = new Log_Entry('ch', 100, 'debug', '2024-01-01 00:00:00');
        $errorEntry = new Log_Entry('ch', 400, 'error', '2024-01-01 00:00:00');

        $this->assertFalse($handler->should_handle($debugEntry));
        $this->assertTrue($handler->should_handle($errorEntry));
    }

    public function test_should_handle_filters_by_channel(): void
    {
        $handler = $this->createConcreteHandler();
        $handler->set_channels(['payments', 'api']);

        $allowedEntry = new Log_Entry('payments', 200, 'msg', '2024-01-01 00:00:00');
        $deniedEntry  = new Log_Entry('auth', 200, 'msg', '2024-01-01 00:00:00');

        $this->assertTrue($handler->should_handle($allowedEntry));
        $this->assertFalse($handler->should_handle($deniedEntry));
    }

    public function test_should_handle_combines_level_and_channel(): void
    {
        $handler = $this->createConcreteHandler(LogLevel::WARNING);
        $handler->set_channels(['api']);

        $this->assertFalse($handler->should_handle(
            new Log_Entry('api', 200, 'info', '2024-01-01 00:00:00')
        ));
        $this->assertFalse($handler->should_handle(
            new Log_Entry('auth', 300, 'warn', '2024-01-01 00:00:00')
        ));
        $this->assertTrue($handler->should_handle(
            new Log_Entry('api', 300, 'warn', '2024-01-01 00:00:00')
        ));
    }

    public function test_set_buffer_limit(): void
    {
        $handler = $this->createConcreteHandler();
        $this->assertSame($handler, $handler->set_buffer_limit(50));
    }

    public function test_set_formatter(): void
    {
        $formatter = new class implements Formatter_Interface {
            public function format(Log_Entry $entry): string
            {
                return 'test';
            }
        };

        $handler = $this->createConcreteHandler();
        $this->assertSame($handler, $handler->set_formatter($formatter));
    }

    public function test_set_channels_filters_empty_values(): void
    {
        $handler = $this->createConcreteHandler();
        $handler->set_channels(['api', '', null]);

        // The handler should be allowed for 'api' but the empty string should be filtered.
        $this->assertTrue($handler->should_handle(
            new Log_Entry('api', 100, 'msg', '2024-01-01 00:00:00')
        ));
    }

    /**
     * Create a concrete handler instance for testing.
     */
    private function createConcreteHandler(
        string $min_level = LogLevel::DEBUG,
        int $buffer_limit = 0,
    ): Abstract_Handler {
        return new class($min_level, $buffer_limit) extends Abstract_Handler
        {
            public function __construct(string $min_level, int $buffer_limit)
            {
                parent::__construct($min_level, $buffer_limit);
            }

            protected function write(array $entries): void
            {
                // No-op: this test double only exercises level/channel filtering.
            }
        };
    }
}
