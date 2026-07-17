<?php
/**
 * Formats a log record into a single line of text.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Formatters;

use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Contracts\Formatter_Interface;
use WPTechnix\WP_Simple_Logger\Utils\Json_Encoder;
use Override;

/**
 * Class Line_Formatter.
 *
 * Formats a Log_Entry object into a customizable single-line string.
 * This is the default formatter for handlers like File_Handler and Email_Handler.
 */
final class Line_Formatter implements Formatter_Interface {

	/**
	 * Default Line Format.
	 */
	private const DEFAULT_FORMAT = '[%timestamp%] %channel%.%level_name%: %message% %context%' . PHP_EOL;

	/**
	 * Line Format.
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
	 * Formats a log record into a line string.
	 *
	 * @param Log_Entry $entry A structured log record entity.
	 *
	 * @return string The formatted log string.
	 */
	#[Override]
	public function format( Log_Entry $entry ): string {
		$context        = $entry->get_context();
		$context_string = '';

		if ( null !== $context ) {
			$json           = Json_Encoder::encode( $context );
			$context_string = $json ?? '[json_encode failed]';
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
