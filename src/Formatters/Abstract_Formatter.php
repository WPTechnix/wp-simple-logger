<?php
/**
 * Abstract base class for all log formatters.
 *
 * @package WPTechnix\WP_Simple_Logger\Formatters
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Formatters;

use WPTechnix\WP_Simple_Logger\Contracts\Formatter_Interface;
use WPTechnix\WP_Simple_Logger\Log_Entry;

/**
 * Class Abstract_Formatter.
 *
 * Provides the contract for concrete formatter implementations.
 */
abstract class Abstract_Formatter implements Formatter_Interface {

	/**
	 * Formats a log record into a string.
	 *
	 * @param Log_Entry $entry A structured log record entity.
	 *
	 * @return string The formatted string representation of the log record.
	 */
	abstract public function format( Log_Entry $entry ): string;
}
