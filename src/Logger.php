<?php
/**
 * PSR-3 Compliant Logger implementation.
 *
 * @package WPTechnix\WP_Simple_Logger
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Stringable;
use Override;

/**
 * Class Logger.
 *
 * An implementation of PSR-3's LoggerInterface. This class acts as a lightweight
 * proxy, delegating all log calls to the central Log_Manager instance for a specific channel.
 */
final class Logger extends AbstractLogger {

	/**
	 * Logger constructor.
	 *
	 * @param string      $channel_name The name of this logger channel.
	 * @param Log_Manager $manager      The central Log_Manager instance to delegate logging to.
	 */
	public function __construct(
		private string $channel_name,
		private Log_Manager $manager
	) {}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed                   $level   The PSR-3 log level.
	 * @param string|Stringable       $message The log message.
	 * @param array<array-key, mixed> $context The context data.
	 *
	 * @throws InvalidArgumentException When an unsupported log level is supplied.
	 */
	#[Override]
	public function log( $level, string|Stringable $message, array $context = [] ): void {
		if ( ! is_string( $level ) || ! Log_Level::is_valid_level( $level ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Invalid log level: %s. Use one of the PSR-3 levels (see \\Psr\\Log\\LogLevel).',
					is_string( $level ) ? $level : gettype( $level )
				)
			);
		}

		$this->manager->log( $this->channel_name, $level, (string) $message, $context );
	}
}
