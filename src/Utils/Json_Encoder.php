<?php
/**
 * Centralized JSON encoding with Monolog-compatible default flags.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Utils;

/**
 * Class Json_Encoder.
 *
 * A thin, stateless wrapper around `wp_json_encode()` that applies the same
 * default flag set Monolog's `Utils::jsonEncode()` uses, so encoded output
 * (unescaped slashes/unicode, preserved float precision, substituted invalid
 * UTF-8) stays consistent across every formatter and handler in this library.
 */
final class Json_Encoder {

	/**
	 * The default JSON encoding flags applied unless the caller overrides them.
	 */
	public const DEFAULT_FLAGS = JSON_UNESCAPED_SLASHES
		| JSON_UNESCAPED_UNICODE
		| JSON_PRESERVE_ZERO_FRACTION
		| JSON_INVALID_UTF8_SUBSTITUTE
		| JSON_PARTIAL_OUTPUT_ON_ERROR;

	/**
	 * Encodes a value to a JSON string.
	 *
	 * @param mixed $data  The value to encode.
	 * @param int   $flags Bitmask of `JSON_*` encoding options.
	 *
	 * @return string|null The encoded JSON string, or null if encoding failed.
	 */
	public static function encode( mixed $data, int $flags = self::DEFAULT_FLAGS ): ?string {
		$json = wp_json_encode( $data, $flags );

		return is_string( $json ) ? $json : null;
	}
}
