<?php
/**
 * Generic handler that posts logs as structured JSON to any HTTP endpoint.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Handlers;

use WPTechnix\WP_Simple_Logger\Log_Entry;
use Override;

/**
 * Class Webhook_Handler.
 *
 * Posts buffered log records to an arbitrary endpoint as a single JSON document
 * shaped as `{ "logs": [ ... ] }`. This is a convenient building block for shipping
 * logs to custom ingestion services or third-party platforms.
 */
final class Webhook_Handler extends Abstract_Webhook_Handler {

	/**
	 * Builds a structured JSON payload containing every buffered log entry.
	 *
	 * @param array<int, Log_Entry> $entries The buffered log entries to dispatch.
	 *
	 * @return array<array-key, mixed> The payload with a `logs` array of records.
	 */
	#[Override]
	protected function build_payload( array $entries ): array {
		return [
			'logs' => array_map( fn ( Log_Entry $entry ): array => $this->map_entry( $entry ), $entries ),
		];
	}

	/**
	 * Converts a log entry into a plain associative array of its fields.
	 *
	 * @param Log_Entry $entry The log entry to convert.
	 *
	 * @return array<string, mixed> The record representation of the entry.
	 */
	private function map_entry( Log_Entry $entry ): array {
		return [
			'channel'    => $entry->get_channel(),
			'level'      => $entry->get_level_priority(),
			'level_name' => $entry->get_level_name(),
			'message'    => $entry->get_message(),
			'context'    => $entry->get_context(),
			'datetime'   => $entry->get_date_time(),
			'timestamp'  => strtotime( $entry->get_date_time() ),
		];
	}
}
