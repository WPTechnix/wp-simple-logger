<?php
/**
 * Formats a log record into a single line of text.
 *
 * @package WPTechnix\WP_Simple_Logger\Formatters
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Formatters;

use WPTechnix\WP_Simple_Logger\Log_Entry;

/**
 * Class Line_Formatter.
 *
 * Formats a Log_Entry object into a customizable single-line string.
 * This is the default formatter for handlers like File_Handler and Email_Handler.
 */
final class Line_Formatter extends Abstract_Formatter {

	/**
	 * Default Line Format.
	 */
	private const DEFAULT_FORMAT = '[%timestamp%] %channel%.%level_name%: %message% %context%' . PHP_EOL;

	/**
	 * Line Format.
	 *
	 * @var string
	 */
	private string $format;

	/**
	 * Line_Formatter constructor.
	 *
	 * @param string|null $format      The format string with placeholders.
	 * @param bool        $ignore_empty_context If true, empty context arrays will not be appended.
	 */
	public function __construct(
		?string $format = null,
		private bool $ignore_empty_context = true
	) {
		$this->format = $format ?? self::DEFAULT_FORMAT;
	}

	/**
	 * Formats a log record into a string.
	 *
	 * @param Log_Entry $entry A structured log record entity.
	 *
	 * @return string The formatted log string.
	 */
	public function format( Log_Entry $entry ): string {
		$context        = $entry->get_context();
		$context_string = '';

		if ( null !== $context ) {
			$json           = wp_json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			$context_string = is_string( $json ) ? $json : '[json_encode failed]';
		} elseif ( true === $this->ignore_empty_context ) {
			// Remove the %context% placeholder (and a single preceding space) from the
			// template before substituting the remaining placeholders.
			$format = str_replace( [ ' %context%', '%context%' ], '', $this->format );
			return $this->get_formatted_line( $entry, '', $format );
		}

		return $this->get_formatted_line( $entry, $context_string );
	}

	/**
	 * Helper to perform the string replacement.
	 *
	 * @param Log_Entry   $entry The log entry.
	 * @param string      $context_string The formatted context string.
	 * @param string|null $format The format template to use. Defaults to the configured format.
	 * @return string The fully formatted line.
	 */
	private function get_formatted_line( Log_Entry $entry, string $context_string, ?string $format = null ): string {
		$replacements = [
			'%timestamp%'  => $entry->get_formatted_date_time(),
			'%channel%'    => $entry->get_channel(),
			'%level_name%' => strtoupper( $entry->get_level_name() ),
			'%message%'    => $entry->get_message(),
			'%context%'    => $context_string,
		];

		return strtr( $format ?? $this->format, $replacements );
	}
}
