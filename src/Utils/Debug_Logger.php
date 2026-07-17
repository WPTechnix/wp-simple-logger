<?php
/**
 * Reports internal library failures to the PHP error log when debugging is enabled.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Utils;

/**
 * Class Debug_Logger.
 *
 * A single, shared place for the "only log when WP_DEBUG is enabled" pattern
 * used across the library's own internal failure paths (a handler throwing,
 * a webhook transport failing, a file write failing, an email failing to
 * send). This is deliberately separate from the library's own log handlers:
 * it is for reporting failures *of* the logging library itself, not for
 * application log records.
 */
final class Debug_Logger {

	/**
	 * Writes a message to the PHP error log, but only when WP_DEBUG is enabled.
	 *
	 * @param string $message The message to report.
	 */
	public static function log( string $message ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
