<?php
/**
 * Interface for all log handlers.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Contracts;

use WPTechnix\WP_Simple_Logger\Log_Entry;

/**
 * Interface Handler_Interface.
 *
 * Defines the contract that all logging handlers must follow, ensuring
 * they can be managed by the central Log_Manager.
 */
interface Handler_Interface {

	/**
	 * Checks whether the given log entry should be handled by this handler.
	 *
	 * @param Log_Entry $entry The log entry to check.
	 *
	 * @return bool True if the handler should handle this log, false otherwise.
	 */
	public function should_handle( Log_Entry $entry ): bool;

	/**
	 * Handles a log record.
	 * This is where the record is buffered, formatted, or sent to its final destination.
	 *
	 * @param Log_Entry $entry The log entry instance to handle.
	 *
	 * @return bool True if the record was successfully handled.
	 */
	public function handle( Log_Entry $entry ): bool;

	/**
	 * Flushes all buffered logs to their final destination.
	 *
	 * This method is called at the end of the request and is where all slow
	 * I/O operations (file writes, DB inserts, API calls) should occur.
	 */
	public function flush(): void;
}
