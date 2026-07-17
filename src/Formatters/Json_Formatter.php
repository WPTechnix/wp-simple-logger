<?php
/**
 * Formats a log record into a JSON string.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Formatters;

use WPTechnix\WP_Simple_Logger\Contracts\Formatter_Interface;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Utils\Json_Encoder;
use stdClass;
use Override;

/**
 * Class Json_Formatter.
 *
 * Serializes a Log_Entry object into a JSON string.
 * This is useful for sending logs to services that ingest structured JSON.
 */
final class Json_Formatter implements Formatter_Interface {

	/**
	 * The default set of keys to include in the JSON output.
	 */
	private const DEFAULT_KEYS = [ 'datetime', 'channel', 'level', 'levelName', 'message', 'context' ];

	/**
	 * A whitelist of keys to include in the final JSON record.
	 *
	 * @var list<string>
	 */
	private array $keys_to_include;

	/**
	 * Json_Formatter constructor.
	 *
	 * @param list<string>|null $keys_to_include An array of keys to include in the output.
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
	#[Override]
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

		$json = Json_Encoder::encode( $record );

		return null !== $json ? $json . PHP_EOL : '';
	}
}
