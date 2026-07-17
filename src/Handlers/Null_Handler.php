<?php
/**
 * A no-op log handler that discards every record.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Handlers;

use WPTechnix\WP_Simple_Logger\Log_Entry;
use Override;

/**
 * Class Null_Handler.
 *
 * Accepts and silently discards all log records. Useful for disabling logging in a
 * particular environment without changing wiring, and as a stand-in during tests.
 */
final class Null_Handler extends Abstract_Handler {

	/**
	 * Accepts the log entry and immediately discards it without buffering.
	 *
	 * @param Log_Entry $entry The log entry instance.
	 *
	 * @return bool Always returns true.
	 */
	#[Override]
	public function handle( Log_Entry $entry ): bool {
		return true;
	}

	/**
	 * Writes the batch to the destination.
	 *
	 * @param array<int, Log_Entry> $entries The buffered log entries.
	 */
	#[Override]
	protected function write( array $entries ): void {
		// Intentionally a no-op: this handler discards every log record.
	}
}
