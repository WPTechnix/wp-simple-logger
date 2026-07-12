<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Support;

use Throwable;
use WPTechnix\WP_Simple_Logger\Contracts\Handler_Interface;
use WPTechnix\WP_Simple_Logger\Log_Entry;

/**
 * A configurable test double that records how the manager interacts with a handler.
 */
final class Spy_Handler implements Handler_Interface
{
    /** @var list<Log_Entry> */
    public array $handled = [];

    public int $flush_count = 0;

    public bool $accept = true;

    public bool $hooks_initialized = false;

    public ?Throwable $throw_on_handle = null;

    public function __construct(bool $accept = true)
    {
        $this->accept = $accept;
    }

    public function should_handle(Log_Entry $entry): bool
    {
        return $this->accept;
    }

    public function handle(Log_Entry $entry): bool
    {
        if (null !== $this->throw_on_handle) {
            throw $this->throw_on_handle;
        }

        $this->handled[] = $entry;
        return true;
    }

    public function flush(): void
    {
        ++$this->flush_count;
    }

    public function init_hooks(): void
    {
        $this->hooks_initialized = true;
    }
}
