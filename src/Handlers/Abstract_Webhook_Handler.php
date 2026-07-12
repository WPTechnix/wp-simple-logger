<?php
/**
 * Abstract base class for handlers that dispatch logs to an HTTP endpoint.
 *
 * @package WPTechnix\WP_Simple_Logger\Handlers
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Handlers;

use Psr\Log\LogLevel;
use WP_Error;
use WPTechnix\WP_Simple_Logger\Log_Entry;

/**
 * Class Abstract_Webhook_Handler.
 *
 * Buffers log records and dispatches them to an HTTP endpoint as a single JSON
 * request using the WordPress HTTP API. The HTTP method, headers, timeout, and
 * blocking behavior are all configurable. Concrete handlers only have to build the
 * payload; buffering, level/channel filtering, and transport are handled here.
 */
abstract class Abstract_Webhook_Handler extends Abstract_Handler {

	/**
	 * The default request timeout, in seconds.
	 */
	protected const DEFAULT_TIMEOUT = 5;

	/**
	 * The endpoint URL that log payloads are sent to.
	 *
	 * @var string
	 */
	protected string $webhook_url;

	/**
	 * The HTTP method used for the request.
	 *
	 * @var string
	 */
	protected string $method = 'POST';

	/**
	 * The request headers, keyed by header name.
	 *
	 * @var array<string, string>
	 */
	protected array $headers = [ 'Content-Type' => 'application/json' ];

	/**
	 * The request timeout, in seconds.
	 *
	 * @var float
	 */
	protected float $timeout = self::DEFAULT_TIMEOUT;

	/**
	 * Whether the HTTP request should block until a response is received.
	 *
	 * @var bool
	 */
	protected bool $blocking = false;

	/**
	 * Additional arguments merged into the `wp_remote_request()` request.
	 * Managed arguments (method, headers, body, timeout, blocking) take precedence.
	 *
	 * @var array<string, mixed>
	 */
	protected array $request_args = [];

	/**
	 * Abstract_Webhook_Handler constructor.
	 *
	 * @param string $webhook_url  The endpoint URL to send logs to.
	 * @param string $min_level    The minimum PSR-3 log level to handle.
	 * @param int    $buffer_limit The number of records to buffer before flushing. 0 for no buffer limit.
	 */
	public function __construct(
		string $webhook_url,
		string $min_level = LogLevel::DEBUG,
		int $buffer_limit = 10
	) {
		parent::__construct( $min_level, $buffer_limit );
		$this->webhook_url = $webhook_url;
	}

	/**
	 * Sets the HTTP method used for the request (e.g. POST, PUT, PATCH).
	 *
	 * @param string $method The HTTP method.
	 */
	public function set_method( string $method ): self {
		$this->method = strtoupper( $method );
		return $this;
	}

	/**
	 * Replaces all request headers.
	 *
	 * @param array<string, string> $headers The headers, keyed by name.
	 */
	public function set_headers( array $headers ): self {
		$this->headers = $headers;
		return $this;
	}

	/**
	 * Adds or overrides a single request header.
	 *
	 * @param string $name  The header name.
	 * @param string $value The header value.
	 */
	public function add_header( string $name, string $value ): self {
		$this->headers[ $name ] = $value;
		return $this;
	}

	/**
	 * Sets the request timeout, in seconds.
	 *
	 * @param float $seconds The timeout in seconds.
	 */
	public function set_timeout( float $seconds ): self {
		$this->timeout = $seconds;
		return $this;
	}

	/**
	 * Sets whether the HTTP request should block until a response is received.
	 *
	 * @param bool $blocking True to wait for the response, false for fire-and-forget.
	 */
	public function set_blocking( bool $blocking ): self {
		$this->blocking = $blocking;
		return $this;
	}

	/**
	 * Sets additional arguments to merge into the `wp_remote_request()` call, for
	 * settings not covered by the dedicated setters (e.g. `sslverify`, `cookies`).
	 *
	 * @param array<string, mixed> $args Additional request arguments.
	 */
	public function set_request_args( array $args ): self {
		$this->request_args = $args;
		return $this;
	}

	/**
	 * Builds the JSON payload for the batch and sends it to the endpoint.
	 *
	 * @param array<int, Log_Entry> $entries The buffered log entries to write.
	 */
	protected function write( array $entries ): void {
		$payload = $this->build_payload( $entries );
		if ( 0 === count( $payload ) ) {
			return;
		}

		$body = wp_json_encode( $payload );
		if ( ! is_string( $body ) ) {
			return;
		}

		$args = array_merge(
			$this->request_args,
			[
				'method'   => $this->method,
				'headers'  => $this->headers,
				'body'     => $body,
				'timeout'  => $this->timeout,
				'blocking' => $this->blocking,
			]
		);

		$this->report_transport_error( wp_remote_request( $this->webhook_url, $args ) );
	}

	/**
	 * Logs a transport-level failure to the PHP error log when debugging is enabled.
	 *
	 * @param array<string, mixed>|WP_Error $response The response from the HTTP request.
	 */
	private function report_transport_error( array|WP_Error $response ): void {
		if ( ! is_wp_error( $response ) ) {
			return;
		}

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf( 'WP Simple Logger: webhook request to %s failed: %s', $this->webhook_url, $response->get_error_message() )
		);
	}

	/**
	 * Builds the JSON-serializable payload from the buffered log entries.
	 *
	 * @param array<int, Log_Entry> $entries The buffered log entries to dispatch.
	 *
	 * @return array<array-key, mixed> The payload to encode and send. Return an empty array to skip the request.
	 */
	abstract protected function build_payload( array $entries ): array;
}
