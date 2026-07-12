<?php
/**
 * Formats a log record into a JSON string.
 *
 * @package WPTechnix\WP_Simple_Logger\Formatters
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Formatters;

use WPTechnix\WP_Simple_Logger\Log_Entry;
use stdClass;

/**
 * Class Json_Formatter.
 *
 * Serializes a Log_Entry object into a JSON string.
 * This is useful for sending logs to services that ingest structured JSON.
 */
final class Json_Formatter extends Abstract_Formatter {

	/**
	 * The default set of keys to include in the JSON output.
	 */
	private const DEFAULT_KEYS = [ 'datetime', 'channel', 'level', 'levelName', 'message', 'context' ];

	/**
	 * A whitelist of keys to include in the final JSON record.
	 *
	 * @var array<string>
	 */
	private array $keys_to_include;

	/**
	 * Json_Formatter constructor.
	 *
	 * @param array<string>|null $keys_to_include An array of keys to include in the output.
	 *                                       Available keys: id, datetime, timestamp, channel, level, levelName, message, context.
	 *                                       If null, a default set will be used.
	 */
	public function __construct( ?array $keys_to_include = null ) {
		$this->keys_to_include = $keys_to_include ?? self::DEFAULT_KEYS;
	}

	/**
	 * Formats a log record into a JSON string.
	 *
	 * @param Log_Entry $entry A structured log record entity.
	 *
	 * @return string The JSON formatted log string.
	 */
	public function format( Log_Entry $entry ): string {
		$datetime_string = $entry->get_date_time();

		// Create a data pool of all possible values.
		$data_pool = [
			'id'        => $entry->get_id(),
			'datetime'  => $datetime_string,
			'timestamp' => strtotime( $datetime_string ),
			'channel'   => $entry->get_channel(),
			'level'     => $entry->get_level_priority(),
			'levelName' => $entry->get_level_name(),
			'message'   => $entry->get_message(),
			'context'   => $entry->get_context() ?? new stdClass(),
		];

		// Build the final record by picking only the whitelisted keys.
		$record = [];
		foreach ( $this->keys_to_include as $key ) {
			// The `id` is nullable, so we check for its existence in the pool.
			if ( array_key_exists( $key, $data_pool ) ) {
				$record[ $key ] = $data_pool[ $key ];
			}
		}

		$json = wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return is_string( $json ) ? $json . PHP_EOL : '';
	}
}
