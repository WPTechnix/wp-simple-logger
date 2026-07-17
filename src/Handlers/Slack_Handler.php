<?php
/**
 * Log handler that posts records to a Slack Incoming Webhook.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Handlers;

use Psr\Log\LogLevel;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Log_Level;
use WPTechnix\WP_Simple_Logger\Utils\Json_Encoder;
use Override;

/**
 * Class Slack_Handler.
 *
 * Buffers log records and posts them to a Slack Incoming Webhook. Each record is
 * rendered as a color-coded attachment whose color reflects the log severity, making
 * high-priority alerts easy to spot in a channel.
 */
final class Slack_Handler extends Abstract_Webhook_Handler {

	/**
	 * The maximum number of context JSON characters to include per attachment.
	 */
	private const MAX_CONTEXT_LENGTH = 1_500;

	/**
	 * Optional bot username to display in Slack.
	 */
	private ?string $username = null;

	/**
	 * Optional emoji icon (e.g. ":warning:") to display next to the message.
	 */
	private ?string $icon_emoji = null;

	/**
	 * Optional channel override (e.g. "#alerts").
	 */
	private ?string $channel = null;

	/**
	 * Slack_Handler constructor.
	 *
	 * @param string $webhook_url  The Slack Incoming Webhook URL.
	 * @param string $min_level    The minimum PSR-3 log level to handle. Defaults to error.
	 * @param int    $buffer_limit The number of records to buffer before sending. 0 for no buffer limit.
	 */
	public function __construct(
		string $webhook_url,
		string $min_level = LogLevel::ERROR,
		int $buffer_limit = 10
	) {
		parent::__construct( $webhook_url, $min_level, $buffer_limit );

		// Identify the source of the alerts by default; override with set_username().
		$this->username = 'WP Simple Logger';
	}

	/**
	 * Sets the bot username shown in Slack.
	 *
	 * @param string $username The username to display.
	 */
	public function set_username( string $username ): self {
		$this->username = $username;
		return $this;
	}

	/**
	 * Sets the emoji icon shown next to the Slack message.
	 *
	 * @param string $icon_emoji The emoji shortcode, e.g. ":rotating_light:".
	 */
	public function set_icon_emoji( string $icon_emoji ): self {
		$this->icon_emoji = $icon_emoji;
		return $this;
	}

	/**
	 * Sets a channel override for the message.
	 *
	 * @param string $channel The target channel, e.g. "#alerts".
	 */
	public function set_channel( string $channel ): self {
		$this->channel = $channel;
		return $this;
	}

	/**
	 * Builds the Slack Incoming Webhook payload from the buffered log entries.
	 *
	 * @param array<int, Log_Entry> $entries The buffered log entries to dispatch.
	 *
	 * @return array<string, mixed> The Slack message payload.
	 */
	#[Override]
	protected function build_payload( array $entries ): array {
		$payload = [
			'attachments' => array_map(
				fn ( Log_Entry $entry ): array => $this->build_attachment( $entry ),
				$entries
			),
		];

		if ( null !== $this->username ) {
			$payload['username'] = $this->username;
		}
		if ( null !== $this->icon_emoji ) {
			$payload['icon_emoji'] = $this->icon_emoji;
		}
		if ( null !== $this->channel ) {
			$payload['channel'] = $this->channel;
		}

		return $payload;
	}

	/**
	 * Builds a single Slack attachment for a log entry.
	 *
	 * @param Log_Entry $entry The log entry to render.
	 *
	 * @return array<string, mixed> The Slack attachment structure.
	 */
	private function build_attachment( Log_Entry $entry ): array {
		$level_name = strtoupper( $entry->get_level_name() );

		return [
			'color'  => Log_Level::get_level_color( $entry->get_level_priority() ),
			'title'  => $level_name,
			'text'   => $this->build_attachment_text( $entry ),
			'ts'     => strtotime( $entry->get_date_time() ),
			'fields' => [
				[
					'title' => 'Channel',
					'value' => $entry->get_channel(),
					'short' => true,
				],
				[
					'title' => 'Time',
					'value' => $entry->get_formatted_date_time(),
					'short' => true,
				],
			],
			'footer' => 'WP Simple Logger',
		];
	}

	/**
	 * Builds the attachment text: the message plus an optional context code block.
	 *
	 * @param Log_Entry $entry The log entry to render.
	 *
	 * @return string The attachment text.
	 */
	private function build_attachment_text( Log_Entry $entry ): string {
		$text    = $entry->get_message();
		$context = $entry->get_context();

		if ( null === $context ) {
			return $text;
		}

		$json = Json_Encoder::encode( $context, Json_Encoder::DEFAULT_FLAGS | JSON_PRETTY_PRINT );
		if ( null === $json ) {
			return $text;
		}

		if ( strlen( $json ) > self::MAX_CONTEXT_LENGTH ) {
			$json = substr( $json, 0, self::MAX_CONTEXT_LENGTH ) . "\n... (truncated)";
		}

		return $text . "\n```\n" . $json . "\n```";
	}
}
