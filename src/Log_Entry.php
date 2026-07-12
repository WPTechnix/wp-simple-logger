<?php
/**
 * A type-safe entity representing a single log record.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger;

/**
 * Class Log_Entry.
 *
 * A pure Data Transfer Object (DTO) that encapsulates the data for a single log entry.
 * This ensures type safety and a consistent data structure throughout the system.
 */
final class Log_Entry {

	/**
	 * Log_Entry constructor.
	 *
	 * @param string                       $channel        The channel name for the log entry.
	 * @param int                          $level_priority The integer priority of the log level.
	 * @param string                       $message        The main log message.
	 * @param string                       $date_time      The UTC datetime string of the log entry in 'Y-m-d H:i:s' format.
	 * @param array<array-key, mixed>|null $context        The normalized context data array.
	 * @param string|int|null              $id             The unique identifier for the log entry, if it exists.
	 */
	public function __construct(
		private string $channel,
		private int $level_priority,
		private string $message,
		private string $date_time,
		private ?array $context = null,
		private int|string|null $id = null
	) {}

	/**
	 * Gets the log entry ID.
	 *
	 * @return null|int|string The ID.
	 */
	public function get_id(): int|string|null {
		return $this->id;
	}

	/**
	 * Gets the channel name.
	 *
	 * @return string The channel.
	 */
	public function get_channel(): string {
		return $this->channel;
	}

	/**
	 * Gets the integer priority of the log level.
	 *
	 * @return int The level priority.
	 */
	public function get_level_priority(): int {
		return $this->level_priority;
	}

	/**
	 * Gets the log level as a string (e.g., 'INFO').
	 *
	 * @return string The log level.
	 */
	public function get_level_name(): string {
		return Log_Level::get_level_from_priority( $this->level_priority );
	}

	/**
	 * Gets the log message.
	 *
	 * @return string The message.
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Gets the normalized context data as an array.
	 *
	 * @return array<array-key, mixed>|null The context array.
	 */
	public function get_context(): ?array {
		return $this->context;
	}

	/**
	 * Gets the log UTC date time string.
	 *
	 * @return string The datetime string in 'Y-m-d H:i:s' format.
	 */
	public function get_date_time(): string {
		return $this->date_time;
	}

	/**
	 * Gets the log datetime string converted to the site's local timezone and desired format.
	 *
	 * @param null|string $format The PHP date format string. (Optional).
	 *                            Defaults to WordPress date and time format.
	 *
	 * @return string The formatted timestamp string.
	 */
	public function get_formatted_date_time( ?string $format = null ): string {
		if ( null === $format ) {
			$date_format = get_option( 'date_format', 'Y-m-d' );
			$date_format = is_string( $date_format ) ? $date_format : 'Y-m-d';
			$time_format = get_option( 'time_format', 'H:i:s' );
			$time_format = is_string( $time_format ) ? $time_format : 'H:i:s';

			$format = $date_format . ' ' . $time_format;
		}

		// get_date_from_gmt expects a GMT/UTC timestamp.
		return get_date_from_gmt( $this->date_time, $format );
	}
}
