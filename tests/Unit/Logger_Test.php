<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit;

use Psr\Log\InvalidArgumentException;
use Stringable;
use WPTechnix\WP_Simple_Logger\Log_Manager;
use WPTechnix\WP_Simple_Logger\Tests\Support\Spy_Handler;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Logger_Test extends TestCase
{
    private Log_Manager $manager;

    private Spy_Handler $spy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new Log_Manager();
        $this->spy = new Spy_Handler();
        $this->manager->add_handler($this->spy);
    }

    public function test_info_delegates_with_channel_level_and_context(): void
    {
        $logger = $this->manager->get_logger('payments');
        $logger->info('User {id} paid', ['id' => 7]);

        $this->assertCount(1, $this->spy->handled);
        $entry = $this->spy->handled[0];
        $this->assertSame('payments', $entry->get_channel());
        $this->assertSame('info', $entry->get_level_name());
        $this->assertSame('User 7 paid', $entry->get_message());
        $this->assertSame(['id' => 7], $entry->get_context());
    }

    public function test_interpolation_replaces_multiple_placeholders(): void
    {
        $logger = $this->manager->get_logger('ch');
        $logger->info('{a}/{b}/{c}', ['a' => 1, 'b' => 'two', 'c' => 3.5]);

        $this->assertSame('1/two/3.5', $this->spy->handled[0]->get_message());
    }

    public function test_interpolation_leaves_placeholder_when_key_missing(): void
    {
        $logger = $this->manager->get_logger('ch');
        $logger->info('Hello {name}', ['other' => 'x']);

        $this->assertSame('Hello {name}', $this->spy->handled[0]->get_message());
    }

    public function test_interpolation_skips_non_stringable_values(): void
    {
        $logger = $this->manager->get_logger('ch');
        $logger->info('{arr} and {obj}', ['arr' => [1, 2], 'obj' => new \stdClass()]);

        $this->assertSame('{arr} and {obj}', $this->spy->handled[0]->get_message());
    }

    public function test_interpolation_uses_stringable_object(): void
    {
        $logger = $this->manager->get_logger('ch');
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringified';
            }
        };

        $logger->info('value: {v}', ['v' => $stringable]);

        $this->assertSame('value: stringified', $this->spy->handled[0]->get_message());
    }

    public function test_interpolation_renders_bool_and_null_readably(): void
    {
        $logger = $this->manager->get_logger('ch');
        $logger->info('{t}/{f}/{n}', ['t' => true, 'f' => false, 'n' => null]);

        $this->assertSame('true/false/null', $this->spy->handled[0]->get_message());
    }

    public function test_interpolation_preserves_context_intact(): void
    {
        $logger = $this->manager->get_logger('ch');
        $logger->info('User {id}', ['id' => 7, 'extra' => 'kept']);

        $this->assertSame(['id' => 7, 'extra' => 'kept'], $this->spy->handled[0]->get_context());
    }

    public function test_message_without_placeholders_is_unchanged(): void
    {
        $logger = $this->manager->get_logger('ch');
        $logger->info('plain message', ['id' => 7]);

        $this->assertSame('plain message', $this->spy->handled[0]->get_message());
    }

    public function test_error_level_is_mapped(): void
    {
        $logger = $this->manager->get_logger('api');
        $logger->error('boom');

        $this->assertSame('error', $this->spy->handled[0]->get_level_name());
    }

    public function test_log_throws_on_invalid_level(): void
    {
        $logger = $this->manager->get_logger('ch');

        $this->expectException(InvalidArgumentException::class);
        $logger->log('warn', 'misspelled level');
    }

    public function test_log_throws_on_non_string_level(): void
    {
        $logger = $this->manager->get_logger('ch');

        $this->expectException(InvalidArgumentException::class);
        // @phpstan-ignore-next-line -- intentionally passing an invalid level type.
        $logger->log(400, 'numeric level');
    }

    public function test_stringable_message_is_cast_to_string(): void
    {
        $logger = $this->manager->get_logger('ch');
        $message = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringified';
            }
        };

        $logger->warning($message);

        $this->assertSame('stringified', $this->spy->handled[0]->get_message());
    }
}
