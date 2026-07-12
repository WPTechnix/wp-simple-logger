<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Psr\Log\LoggerInterface;
use RuntimeException;
use WPTechnix\WP_Simple_Logger\Log_Manager;
use WPTechnix\WP_Simple_Logger\Tests\Support\Spy_Handler;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Log_Manager_Test extends TestCase
{
    public function test_get_logger_returns_psr3_logger_and_caches_it(): void
    {
        $manager = new Log_Manager();
        $first = $manager->get_logger('ch');
        $second = $manager->get_logger('ch');

        $this->assertInstanceOf(LoggerInterface::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_add_handler_deduplicates_same_instance(): void
    {
        $manager = new Log_Manager();
        $spy = new Spy_Handler();
        $manager->add_handler($spy);
        $manager->add_handler($spy);

        $manager->get_logger('ch')->info('once');

        $this->assertCount(1, $spy->handled);
    }

    public function test_dispatch_respects_should_handle(): void
    {
        $manager = new Log_Manager();
        $accepting = new Spy_Handler(true);
        $rejecting = new Spy_Handler(false);
        $manager->add_handler($accepting);
        $manager->add_handler($rejecting);

        $manager->get_logger('ch')->info('hi');

        $this->assertCount(1, $accepting->handled);
        $this->assertCount(0, $rejecting->handled);
    }

    public function test_throwing_handler_does_not_break_dispatch(): void
    {
        $manager = new Log_Manager();
        $faulty = new Spy_Handler();
        $faulty->throw_on_handle = new RuntimeException('handler exploded');
        $healthy = new Spy_Handler();
        $manager->add_handler($faulty);
        $manager->add_handler($healthy);

        $manager->get_logger('ch')->info('resilient');

        $this->assertCount(1, $healthy->handled);
    }

    public function test_flush_all_is_idempotent(): void
    {
        $manager = new Log_Manager();
        $spy = new Spy_Handler();
        $manager->add_handler($spy);

        $manager->flush_all();
        $manager->flush_all();

        $this->assertSame(1, $spy->flush_count);
    }

    public function test_flush_all_and_return_location_returns_location(): void
    {
        $manager = new Log_Manager();
        $spy = new Spy_Handler();
        $manager->add_handler($spy);

        $location = $manager->flush_all_and_return_location('https://example.com/next');

        $this->assertSame('https://example.com/next', $location);
        $this->assertSame(1, $spy->flush_count);
    }

    public function test_wp_die_handler_flushes_then_calls_original(): void
    {
        $manager = new Log_Manager();
        $spy = new Spy_Handler();
        $manager->add_handler($spy);

        $called = false;
        $original = function ($message, $title, $args) use (&$called): void {
            $called = true;
        };

        $wrapped = $manager->get_wp_die_handler($original);
        $wrapped('goodbye', '', []);

        $this->assertTrue($called);
        $this->assertSame(1, $spy->flush_count);
    }

    public function test_init_registers_hooks_and_initializes_handler_hooks(): void
    {
        $manager = new Log_Manager();
        $spy = new Spy_Handler();
        $manager->add_handler($spy);

        Actions\expectAdded('shutdown')->once();
        Filters\expectAdded('wp_redirect')->once();
        Filters\expectAdded('wp_die_handler')->once();

        $manager->init();

        $this->assertTrue($spy->hooks_initialized);
    }
}
