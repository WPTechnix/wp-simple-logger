<?php
/**
 * Log handler for writing to a file.
 *
 * @package WPTechnix\WP_Simple_Logger\Handlers
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Handlers;

use Psr\Log\LogLevel;
use WPTechnix\WP_Simple_Logger\Contracts\Formatter_Interface;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Formatters\Line_Formatter;

/**
 * Class File_Handler.
 *
 * Buffers log records and writes them to a server file using a formatter.
 */
final class File_Handler extends Abstract_Handler {

	/**
	 * The absolute path to the log file.
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * File_Handler constructor.
	 *
	 * @param string                   $path         The absolute path to the log file.
	 * @param string                   $min_level    The minimum PSR-3 log level to handle.
	 * @param int                      $buffer_limit The number of records to buffer before flushing. 0 for no buffer limit.
	 * @param Formatter_Interface|null $formatter    The formatter to use. A default Line_Formatter will be created if null.
	 */
	public function __construct(
		string $path,
		string $min_level = LogLevel::DEBUG,
		int $buffer_limit = 0,
		?Formatter_Interface $formatter = null
	) {
		parent::__construct( $min_level, $buffer_limit, $formatter );
		$this->path = $path;

		// Set a default formatter if one was not provided.
		if ( null !== $this->formatter ) {
			return;
		}

		$this->formatter = new Line_Formatter();
	}

	/**
	 * Formats the batch of entries and appends them to the configured file.
	 *
	 * @param array<int, Log_Entry> $entries The buffered log entries to write.
	 */
	protected function write( array $entries ): void {
		if ( null === $this->formatter ) {
			return;
		}

		$content = '';
		foreach ( $entries as $entry ) {
			$content .= $this->formatter->format( $entry );
		}

		$this->write_to_file( $content );
	}

	/**
	 * Writes content to the log file with error handling.
	 *
	 * @param string $content The content to write.
	 */
	private function write_to_file( string $content ): void {
		$dir = dirname( $this->path );

		// Check if directory exists, if not try to create it.
		if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPress.PHP.NoSilencedErrors.Discouraged
			// Check again with is_dir in case of a race condition where another process created it.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'WP Simple Logger: Could not create directory %s.', $dir ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return;
		}

		// Check for writability.
		if ( ! is_writable( $this->path ) && ! is_writable( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'WP Simple Logger: File or directory is not writable: %s.', $this->path ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return;
		}

		// Now write with more confidence.
		@file_put_contents( $this->path, $content, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
