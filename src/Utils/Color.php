<?php
/**
 * Color Util Class
 *
 * @package WPTechnix\WP_Simple_Logger\Utils
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Utils;

/**
 * Class Color
 */
final class Color {

	/**
	 * Determines if a hex color is dark to decide on text color (black or white).
	 *
	 * @param string $hex_color The hex color code.
	 * @return bool True if the color is dark.
	 */
	public static function is_color_dark( string $hex_color ): bool {
		$hex_color = ltrim( $hex_color, '#' );
		if ( 3 === strlen( $hex_color ) ) {
			$hex_color = $hex_color[0] . $hex_color[0] . $hex_color[1] . $hex_color[1] . $hex_color[2] . $hex_color[2];
		}
		$r = (float) hexdec( substr( $hex_color, 0, 2 ) );
		$g = (float) hexdec( substr( $hex_color, 2, 2 ) );
		$b = (float) hexdec( substr( $hex_color, 4, 2 ) );
		// W3C algorithm for luminance.
		$luminance = ( 0.2126 * $r + 0.7152 * $g + 0.0722 * $b ) / 255.0;
		return $luminance < 0.715; // was 0.5.
	}
}
