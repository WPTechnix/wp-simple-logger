<?php
/**
 * Interface for all log record formatters.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Contracts;

use WPTechnix\WP_Simple_Logger\Log_Entry;

/**
 * Interface Formatter_Interface.
 *
 * Defines the contract for classes that transform a log record entity into a
 * string representation.
 */
interface Formatter_Interface {

	/**
	 * Formats a log record into a string.
	 *
	 * @param Log_Entry $entry The log entry entity to format.
	 *
	 * @return string The formatted string representation of the log record.
	 */
	public function format( Log_Entry $entry ): string;
}
