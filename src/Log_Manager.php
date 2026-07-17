<?php
/**
 * The central orchestrator for the logging library.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger;

use Psr\Log\LoggerInterface;
use Throwable;
use WP_Error;
use WPTechnix\WP_Simple_Logger\Contracts\Handler_Interface;
use WPTechnix\WP_Simple_Logger\Contracts\Normalizer_Interface;
use WPTechnix\WP_Simple_Logger\Normalizers\Data_Normalizer;
use WPTechnix\WP_Simple_Logger\Utils\Debug_Logger;
use Stringable;
use __PHP_Incomplete_Class;
use ArrayObject;
use stdClass;

/**
 * Class Log_Manager.
 *
 * Manages all logger channels, handlers, and the flushing of logs.
 * This is the primary entry point for configuring and using the library.
 */
final class Log_Manager {

	/**
	 * A flat list of all unique handler instances.
	 *
	 * @var array<string, Handler_Interface>
	 */
	private array $handlers = [];

	/**
	 * A cache of instantiated Logger objects.
	 *
	 * @var array<string, LoggerInterface>
	 */
	private array $loggers = [];

	/**
	 * A flag to ensure flushing only happens once per request.
	 */
	private bool $has_flushed = false;

	/**
	 * A flag to ensure WordPress hooks are only registered once.
	 */
	private bool $hooks_registered = false;

	/**
	 * The data normalizer instance.
	 */
	private Normalizer_Interface $normalizer;

	/**
	 * Log_Manager constructor.
	 *
	 * @param Normalizer_Interface|null $normalizer The context normalizer to use.
	 *                                               Defaults to a standard Data_Normalizer instance.
	 */
	public function __construct( ?Normalizer_Interface $normalizer = null ) {
		$this->normalizer = $normalizer ?? new Data_Normalizer();
	}

	/**
	 * Adds a new handler to the global stack.
	 * Handlers will listen to all channels by default, unless configured otherwise
	 * via their `set_channels()` method.
	 *
	 * @param Handler_Interface $handler The handler instance to add.
	 */
	public function add_handler( Handler_Interface $handler ): void {
		$handler_hash = spl_object_hash( $handler );
		if ( isset( $this->handlers[ $handler_hash ] ) ) {
			return;
		}

		$this->handlers[ $handler_hash ] = $handler;
	}

	/**
	 * Retrieves a logger instance for a given channel.
	 *
	 * @param string $channel_name The name of the channel to get a logger for.
	 *
	 * @return LoggerInterface The PSR-3 compliant logger instance.
	 */
	public function get_logger( string $channel_name ): LoggerInterface {
		if ( isset( $this->loggers[ $channel_name ] ) ) {
			return $this->loggers[ $channel_name ];
		}

		$this->loggers[ $channel_name ] = new Logger( $channel_name, $this );
		return $this->loggers[ $channel_name ];
	}

	/**
	 * Registers all necessary WordPress hooks for flushing and UI.
	 *
	 * This method should be called once after all handlers have been added.
	 */
	public function init(): void {
		if ( true === $this->hooks_registered ) {
			return;
		}
		$this->hooks_registered = true;

		// Register flushing hooks for maximum reliability.
		add_action( 'shutdown', [ $this, 'flush_all' ], 50 );
		add_filter( 'wp_redirect', [ $this, 'flush_all_and_return_location' ], 10, 1 );
		add_filter( 'wp_die_handler', [ $this, 'get_wp_die_handler' ], 1, 1 );

		// Allow handlers to initialize their own hooks (e.g., for admin viewers).
		foreach ( $this->handlers as $handler ) {
			if ( method_exists( $handler, 'init_hooks' ) ) {
				$handler->init_hooks();
			}
		}
	}

	/**
	 * Creates a Log_Entry and dispatches it to all applicable handlers.
	 * This method is for internal use by the Logger class.
	 *
	 * @param string                  $channel The channel the log belongs to.
	 * @param string                  $level   The PSR-3 log level.
	 * @param string                  $message The log message.
	 * @param array<array-key, mixed> $context The context data.
	 * @internal
	 */
	public function log( string $channel, string $level, string $message, array $context ): void {
		// Interpolate {placeholder} tokens using the raw context before normalization.
		$message = $this->interpolate( $message, $context );

		// Create the canonical Log_Entry object once.
		$entry = new Log_Entry(
			$channel,
			Log_Level::get_level_priority( $level ),
			$message,
			current_time( 'mysql', true ),
			( 0 !== count( $context ) ) ? $this->normalizer->normalize_context( $context ) : null
		);

		foreach ( $this->handlers as $handler ) {
			if ( ! $handler->should_handle( $entry ) ) {
				continue;
			}

			try {
				$handler->handle( $entry );
			} catch ( Throwable $e ) {
				// Prevent a faulty handler from crashing the logging process.
				$this->log_handler_error( 'Error in handler', $handler, $e );
			}
		}
	}

	/**
	 * Replaces {placeholder} tokens in the message with matching context values,
	 * per the PSR-3 interpolation convention.
	 *
	 * Non-stringable values (arrays and objects without `__toString`) are left in
	 * place, and the context itself is never modified, so every original key still
	 * appears in the stored context data.
	 *
	 * @param string                  $message The raw log message, possibly containing {placeholders}.
	 * @param array<array-key, mixed> $context The raw context data.
	 *
	 * @return string The message with recognized placeholders replaced.
	 */
	private function interpolate( string $message, array $context ): string {
		if ( 0 === count( $context ) || ! str_contains( $message, '{' ) ) {
			return $message;
		}

		$replacements = [];
		foreach ( $context as $key => $value ) {
			$placeholder = '{' . $key . '}';
			if ( ! str_contains( $message, $placeholder ) || ! $this->is_stringifiable( $value ) ) {
				continue;
			}

			$replacements[ $placeholder ] = $this->stringify_context_value( $value );
		}

		return strtr( $message, $replacements );
	}

	/**
	 * Determines whether a context value can be safely rendered into a message.
	 *
	 * Arrays and objects without `__toString` are left as their literal tokens.
	 *
	 * @param mixed $value The context value to test.
	 *
	 * @return bool True when the value can be converted to a string.
	 */
	private function is_stringifiable( mixed $value ): bool {
		if ( is_array( $value ) ) {
			return false;
		}

		return ! is_object( $value ) || method_exists( $value, '__toString' );
	}

	/**
	 * Converts a stringifiable context value to its message representation.
	 *
	 * @param mixed $value A scalar, null, or object with `__toString`.
	 *
	 * @return string The string representation used for interpolation.
	 */
	private function stringify_context_value( mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( null === $value ) {
			return 'null';
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		if ( is_object( $value ) ) {
			return $this->stringify_object_value( $value );
		}

		return gettype( $value );
	}

	/**
	 * Converts an object context value to its message representation.
	 *
	 * @param object $value The object to convert.
	 *
	 * @return string The string representation used for interpolation.
	 */
	private function stringify_object_value( object $value ): string {
		if ( $value instanceof Stringable ) {
			try {
				return (string) $value;
			} catch ( Throwable ) {
				// if __toString fails, fall back to the class name.
				return sprintf( '[object(%s) - __toString failed]', $value::class );
			}
		}

		if ( __PHP_Incomplete_Class::class === $value::class ) {
			$accessor = new ArrayObject( $value );

			return sprintf(
				'[Incomplete Class: %s]',
				isset( $accessor['__PHP_Incomplete_Class_Name'] ) && is_string( $accessor['__PHP_Incomplete_Class_Name'] )
					? $accessor['__PHP_Incomplete_Class_Name']
					: 'unknown'
			);
		}

		if ( stdClass::class === $value::class ) {
			return '[object]';
		}

		return sprintf( '[object(%s)]', $value::class );
	}

	/**
	 * Logs a handler failure to the PHP error log when debugging is enabled.
	 *
	 * @param string            $context A short description of what failed.
	 * @param Handler_Interface $handler The handler that raised the error.
	 * @param Throwable         $error   The caught error.
	 */
	private function log_handler_error( string $context, Handler_Interface $handler, Throwable $error ): void {
		Debug_Logger::log(
			sprintf( 'WP Simple Logger: %s %s: %s', $context, $handler::class, $error->getMessage() )
		);
	}

	/**
	 * Triggers the flushing of all buffered logs across all handlers.
	 */
	public function flush_all(): void {
		if ( true === $this->has_flushed ) {
			return;
		}

		foreach ( $this->handlers as $handler ) {
			try {
				$handler->flush();
			} catch ( Throwable $e ) {
				// Never let a flush error bubble up, especially during shutdown or wp_die.
				$this->log_handler_error( 'Error flushing handler', $handler, $e );
			}
		}

		$this->has_flushed = true;
	}

	/**
	 * Callback for the 'wp_redirect' filter to ensure logs are saved before redirecting.
	 *
	 * @param string $location The redirect URL.
	 *
	 * @return string The unmodified redirect URL.
	 */
	public function flush_all_and_return_location( string $location ): string {
		$this->flush_all();
		return $location;
	}

	/**
	 * Returns a callback that flushes logs before `wp_die` terminates execution.
	 *
	 * @param callable $handler The default `wp_die` handler.
	 *
	 * @return callable The custom handler that includes log flushing.
	 */
	public function get_wp_die_handler( callable $handler ): callable {
		return function ( string|WP_Error $message, ?string $title = '', string|array $args = [] ) use ( $handler ): void {
			$this->flush_all();
			// Now call the original handler to terminate execution.
			call_user_func( $handler, $message, $title, $args );
		};
	}
}
