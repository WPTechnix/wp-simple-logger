<?php
/**
 * Utility for handling PSR-3 log levels and their priorities.
 *
 * @package WPTechnix\WP_Simple_Logger
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger;

use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Class Log_Level.
 *
 * Centralizes the mapping between PSR-3 log level strings and their integer
 * severities based on RFC 5424. This ensures consistent priority handling
 * across the entire library.
 */
final class Log_Level {

	/**
	 * A map of PSR-3 log levels to their integer severity.
	 */
	private const LEVELS = [
		PsrLogLevel::DEBUG     => 100,
		PsrLogLevel::INFO      => 200,
		PsrLogLevel::NOTICE    => 250,
		PsrLogLevel::WARNING   => 300,
		PsrLogLevel::ERROR     => 400,
		PsrLogLevel::CRITICAL  => 500,
		PsrLogLevel::ALERT     => 550,
		PsrLogLevel::EMERGENCY => 600,
	];

	/**
	 * Gets the integer priority for a given PSR-3 log level string.
	 *
	 * @param string $level The log level string (e.g., 'info', 'error').
	 *
	 * @return int The integer priority, or 0 if the level is unknown.
	 */
	public static function get_level_priority( string $level ): int {
		return self::LEVELS[ strtolower( $level ) ] ?? 0;
	}

	/**
	 * Determines whether the given string is a recognized PSR-3 log level.
	 *
	 * @param string $level The log level string to validate (case-insensitive).
	 *
	 * @return bool True if the level is one of the eight PSR-3 levels, false otherwise.
	 */
	public static function is_valid_level( string $level ): bool {
		return isset( self::LEVELS[ strtolower( $level ) ] );
	}

	/**
	 * Gets the PSR-3 log level string for a given integer priority.
	 *
	 * This method finds the highest log level for which the given priority is met or
	 * exceeded. For example, a priority of `220` will return `info` (priority 200),
	 * and a priority of `600` or higher will return `emergency` (priority 600).
	 *
	 * If the priority is lower than the lowest defined level (`debug`, 100),
	 * it will default to `debug`.
	 *
	 * @param int $priority The priority value to map to a log level.
	 *
	 * @return string One of the PSR-3 log level strings (e.g., 'debug', 'error').
	 */
	public static function get_level_from_priority( int $priority ): string {
		// Iterate backwards from the highest priority to the lowest.
		foreach ( array_reverse( self::LEVELS, true ) as $level => $level_priority ) {
			// Find the first level where the given priority is greater than or equal to the level's priority.
			if ( $priority >= $level_priority ) {
				return $level;
			}
		}

		// If the priority is lower than any defined level, default to the lowest one.
		return PsrLogLevel::DEBUG;
	}

	/**
	 * Gets all defined log level strings.
	 *
	 * @return array<string> An array of all PSR-3 log level strings.
	 */
	public static function get_all_levels(): array {
		return array_keys( self::LEVELS );
	}

	/**
	 * Translates log levels to hex color codes for HTML formatting.
	 *
	 * @param int $priority The integer priority of the log level.
	 * @return string The hex color code.
	 */
	public static function get_level_color( int $priority ): string {
		return match ( true ) {
			$priority >= self::LEVELS[ PsrLogLevel::EMERGENCY ] => '#000000', // Black.
			$priority >= self::LEVELS[ PsrLogLevel::ALERT ] => '#821722',     // Dark Red.
			$priority >= self::LEVELS[ PsrLogLevel::CRITICAL ] => '#DC3545',  // Red.
			$priority >= self::LEVELS[ PsrLogLevel::ERROR ] => '#FD7E14',     // Orange.
			$priority >= self::LEVELS[ PsrLogLevel::WARNING ] => '#FFC107',  // Yellow.
			$priority >= self::LEVELS[ PsrLogLevel::NOTICE ] => '#17A2B8',    // Cyan.
			$priority >= self::LEVELS[ PsrLogLevel::INFO ] => '#28A745',      // Green.
			default => '#6c757d', // Grey for Debug.
		};
	}
}
