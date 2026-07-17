<?php
/**
 * Log handler for sending emails.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Handlers;

use Psr\Log\LogLevel;
use WPTechnix\WP_Simple_Logger\Contracts\Formatter_Interface;
use WPTechnix\WP_Simple_Logger\Formatters\Html_Formatter;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Utils\Debug_Logger;
use Override;

/**
 * Class Email_Handler.
 *
 * Buffers log records and sends them as a single, formatted HTML email.
 */
final class Email_Handler extends Abstract_Handler {

	/**
	 * The email recipient(s).
	 *
	 * @var array<string>
	 */
	private array $to_recipients;

	/**
	 * The email subject line.
	 */
	private string $subject;

	/**
	 * Custom email headers.
	 *
	 * @var array<string>
	 */
	private array $headers;

	/**
	 * The main title of the email body.
	 */
	private string $email_title;

	/**
	 * The introductory paragraph of the email body.
	 */
	private string $email_intro;

	/**
	 * The footer text of the email body.
	 */
	private string $email_footer;

	/**
	 * Email_Handler constructor.
	 *
	 * @param string|array<string>     $to_recipients The email address or array of addresses.
	 * @param string                   $subject       The subject line for the email.
	 * @param string                   $min_level     The minimum PSR-3 log level to handle.
	 * @param int                      $buffer_limit  Max number of records to buffer before sending.
	 * @param Formatter_Interface|null $formatter     The formatter to use for log entries.
	 */
	public function __construct(
		string|array $to_recipients,
		string $subject,
		string $min_level = LogLevel::ERROR,
		int $buffer_limit = 10,
		?Formatter_Interface $formatter = null
	) {
		parent::__construct( $min_level, $buffer_limit, $formatter );
		$this->to_recipients = is_array( $to_recipients ) ? $to_recipients : [ $to_recipients ];
		$this->subject       = $subject;

		// Initialize default email content.
		$this->email_title  = 'Application Log Report';
		$this->email_intro  = 'The following high-priority events were recorded:';
		$this->email_footer = 'This is an automated message.';
		$this->headers      = [ 'Content-Type: text/html; charset=UTF-8' ];

		// Set a default formatter if one was not provided.
		if ( null !== $this->formatter ) {
			return;
		}

		$this->formatter = new Html_Formatter();
	}

	/**
	 * Sets the main title for the HTML email.
	 *
	 * @param string $title The new title.
	 */
	public function set_email_title( string $title ): self {
		$this->email_title = $title;
		return $this;
	}

	/**
	 * Sets the introductory paragraph for the email. Set to an empty string to remove it.
	 *
	 * @param string $intro The new intro text.
	 */
	public function set_email_intro( string $intro ): self {
		$this->email_intro = $intro;
		return $this;
	}

	/**
	 * Sets the footer text for the email. Set to an empty string to remove it.
	 *
	 * @param string $footer The new footer text.
	 */
	public function set_email_footer( string $footer ): self {
		$this->email_footer = $footer;
		return $this;
	}

	/**
	 * Builds a single HTML email from the batch of entries and sends it.
	 *
	 * @param array<int, Log_Entry> $entries The buffered log entries to write.
	 */
	#[Override]
	protected function write( array $entries ): void {
		if ( null === $this->formatter ) {
			return;
		}

		$body  = '<div style="font-family: sans-serif; max-width: 800px; margin: auto; border: 1px solid #ddd; padding: 20px;">';
		$body .= '<h2 style="border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-top: 0;">' . esc_html( $this->email_title ) . '</h2>';

		if ( '' !== $this->email_intro ) {
			$body .= '<p>' . esc_html( $this->email_intro ) . '</p>';
		}

		// A sub-container for the logs themselves.
		$body .= '<div style="background-color: #f9f9f9; border: 1px solid #eee; padding: 10px;">';
		foreach ( $entries as $entry ) {
			$body .= $this->formatter->format( $entry );
		}
		$body .= '</div>'; // End log container.

		if ( '' !== $this->email_footer ) {
			$body .= '<p style="font-size: 0.9em; color: #666; margin-top: 20px;">' . esc_html( $this->email_footer ) . '</p>';
		}
		$body .= '</div>';

		$sent = wp_mail( $this->to_recipients, $this->subject, $body, $this->headers );

		if ( false !== $sent ) {
			return;
		}

		Debug_Logger::log( sprintf( 'WP Simple Logger: Failed to send log report email to %s.', implode( ', ', $this->to_recipients ) ) );
	}

	/**
	 * Sets custom headers for the email.
	 *
	 * @param array<string> $headers An array of header strings.
	 */
	public function set_headers( array $headers ): self {
		$this->headers = $headers;
		return $this;
	}
}
