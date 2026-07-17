<?php
/**
 * Abstract base class for all log handlers.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Handlers;

use Psr\Log\LogLevel;
use WPTechnix\WP_Simple_Logger\Contracts\Formatter_Interface;
use WPTechnix\WP_Simple_Logger\Contracts\Handler_Interface;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Log_Level as LogLevelUtil;
use Override;

/**
 * Class Abstract_Handler.
 *
 * Provides the core logic for log level checking, channel scoping, and buffering.
 * This class forms the foundation for all concrete handler implementations.
 */
abstract class Abstract_Handler implements Handler_Interface {

	/**
	 * The minimum log level that this handler will process.
	 */
	protected string $min_level;

	/**
	 * The maximum number of records to buffer before forcing a flush.
	 * A value of 0 disables this feature.
	 */
	protected int $buffer_limit;

	/**
	 * The formatter instance to convert log records to strings.
	 */
	protected ?Formatter_Interface $formatter;

	/**
	 * An array of specific channels this handler should process.
	 * If empty, all channels are allowed.
	 *
	 * @var list<string>
	 */
	protected array $allowed_channels = [];

	/**
	 * In-memory buffer of log entries awaiting a flush.
	 *
	 * @var list<Log_Entry>
	 */
	protected array $buffer = [];

	/**
	 * Abstract_Handler constructor.
	 *
	 * @param string                   $min_level    The minimum PSR-3 log level to accept.
	 * @param int                      $buffer_limit The number of records to buffer before flushing. 0 for no buffer limit.
	 * @param Formatter_Interface|null $formatter    The formatter instance. A default will be created if null in concrete classes.
	 */
	public function __construct( string $min_level = LogLevel::DEBUG, int $buffer_limit = 0, ?Formatter_Interface $formatter = null ) {
		if ( ! LogLevelUtil::is_valid_level( $min_level ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Invalid minimum log level: %s. Use one of the PSR-3 levels (see \\Psr\\Log\\LogLevel).', $min_level )
			);
		}

		$this->min_level    = $min_level;
		$this->buffer_limit = $buffer_limit;
		$this->formatter    = $formatter;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function should_handle( Log_Entry $entry ): bool {
		if ( ! $this->is_channel_allowed( $entry->get_channel() ) ) {
			return false;
		}

		$min_level_priority = LogLevelUtil::get_level_priority( $this->min_level );
		$level_priority     = $entry->get_level_priority();

		return $level_priority >= $min_level_priority;
	}

	/**
	 * Checks if this handler is allowed to process a given channel.
	 *
	 * @param string $channel The channel name to check.
	 *
	 * @return bool True if the channel is allowed.
	 */
	protected function is_channel_allowed( string $channel ): bool {
		if ( 0 === count( $this->allowed_channels ) ) {
			return true; // If the list is empty, all channels are allowed by default.
		}
		return in_array( $channel, $this->allowed_channels, true );
	}

	/**
	 * Restricts this handler to only process logs from the specified channels.
	 *
	 * @param array<string> $channels An array of channel names.
	 */
	public function set_channels( array $channels ): self {
		$this->allowed_channels = array_values( array_unique( array_filter( $channels, static fn ( $v ) => '' !== $v ) ) );
		return $this;
	}

	/**
	 * Sets the maximum number of records to buffer before forcing a flush.
	 *
	 * @param int $limit The buffer size. 0 for no buffer limit.
	 */
	public function set_buffer_limit( int $limit ): self {
		$this->buffer_limit = $limit;
		return $this;
	}

	/**
	 * Sets a new formatter for this handler.
	 *
	 * @param Formatter_Interface $formatter The new formatter instance.
	 */
	public function set_formatter( Formatter_Interface $formatter ): self {
		$this->formatter = $formatter;
		return $this;
	}

	/**
	 * Buffers a log entry and flushes when the buffer limit is reached.
	 *
	 * @param Log_Entry $entry The log entry instance to handle.
	 *
	 * @return bool Always returns true.
	 */
	#[Override]
	public function handle( Log_Entry $entry ): bool {
		$this->buffer[] = $entry;

		if ( 0 !== $this->buffer_limit && count( $this->buffer ) >= $this->buffer_limit ) {
			$this->flush();
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function flush(): void {
		if ( 0 === count( $this->buffer ) ) {
			return;
		}

		$entries      = $this->buffer;
		$this->buffer = [];

		$this->write( $entries );
	}

	/**
	 * Writes a batch of buffered log entries to the handler's destination.
	 *
	 * @param array<int, Log_Entry> $entries The buffered log entries to write.
	 */
	abstract protected function write( array $entries ): void;
}
