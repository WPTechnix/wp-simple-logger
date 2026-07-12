<?php
/**
 * Formats a log record into an HTML table.
 *
 * @package WPTechnix\WP_Simple_Logger\Formatters
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Formatters;

use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Log_Level;
use WPTechnix\WP_Simple_Logger\Utils\Color;

/**
 * Class Html_Formatter.
 *
 * Formats a Log_Entry object into an HTML table.
 * This is especially useful for handlers like Email_Handler.
 */
final class Html_Formatter extends Abstract_Formatter {

	/**
	 * Formats a log record into an HTML string.
	 *
	 * @param Log_Entry $entry A structured log record entity.
	 *
	 * @return string The HTML formatted log string.
	 */
	public function format( Log_Entry $entry ): string {
		$output  = $this->add_title( $entry );
		$output .= '<table cellspacing="1" width="100%" style="border-collapse: collapse; width: 100%;">';
		$output .= $this->add_row( 'Message', $entry->get_message() );
		$output .= $this->add_row( 'Time', $entry->get_formatted_date_time() );
		$output .= $this->add_row( 'Channel', $entry->get_channel() );

		$context = $entry->get_context();
		if ( null !== $context ) {
			$context_json = wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			$context_html = '<pre style="white-space: pre-wrap; word-break: break-all;">' . esc_html( (string) $context_json ) . '</pre>';
			$output      .= $this->add_row( 'Context', $context_html, false );
		}

		return $output . '</table><br>';
	}

	/**
	 * Creates an HTML table row.
	 *
	 * @param string $th       Row header content.
	 * @param string $td       Row standard cell content.
	 * @param bool   $escape_td False if td content must not be html escaped.
	 *
	 * @return string The HTML for a table row.
	 */
	private function add_row( string $th, string $td = ' ', bool $escape_td = true ): string {
		$th = htmlspecialchars( $th, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		if ( true === $escape_td ) {
			$td = htmlspecialchars( $td, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		}

		return '<tr style="padding: 4px;text-align: left;">' .
				'<th style="vertical-align: top; background: #cccccc; color: #000; width: 100px; padding: 5px; border: 1px solid #dddddd;">' . $th . ':</th>' .
				'<td style="padding: 4px;text-align: left;vertical-align: top;background: #eeeeee;color: #000; border: 1px solid #dddddd;">' . $td . '</td>' .
				'</tr>';
	}

	/**
	 * Creates an HTML H1 tag for the log entry title.
	 *
	 * @param Log_Entry $entry The log entry.
	 * @return string The HTML for the title.
	 */
	private function add_title( Log_Entry $entry ): string {
		$level_name  = strtoupper( $entry->get_level_name() );
		$level_color = Log_Level::get_level_color( $entry->get_level_priority() );
		$title       = esc_html( $level_name );
		$text_color  = Color::is_color_dark( $level_color ) ? '#ffffff' : '#000000';

		return '<h3 style="background: ' . $level_color . ';color: ' . $text_color . ';padding: 5px;margin-top: 5px; margin-bottom: 0;">' . $title . '</h3>';
	}
}
