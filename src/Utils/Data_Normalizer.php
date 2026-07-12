<?php
/**
 * Normalizes log context data into a safe, serializable format.
 *
 * @package WPTechnix\WP_Simple_Logger\Utils
 */

declare( strict_types=1 );

namespace WPTechnix\WP_Simple_Logger\Utils;

use Closure;
use DateTimeImmutable;
use JsonSerializable;
use Throwable;
use __PHP_Incomplete_Class;
use ArrayObject;

/**
 * Class Data_Normalizer.
 *
 * Converts complex data types like objects and exceptions in a log context
 * array into a scalar or array representation suitable for serialization.
 * This is heavily inspired by Monolog's normalization process to ensure robustness.
 */
final class Data_Normalizer {

	/**
	 * The maximum number of levels to recurse into a data structure.
	 */
	private const MAX_RECURSION_DEPTH = 9;

	/**
	 * The maximum number of items to show in an array.
	 */
	private const MAX_ARRAY_ITEMS = 1_000;

	/**
	 * Normalizes a log context array.
	 *
	 * @param array<array-key, mixed> $data The raw context data.
	 *
	 * @return array<array-key, mixed> The normalized data.
	 */
	public function normalize_context( array $data ): array {
		return $this->normalize_value( $data, 0 );
	}

	/**
	 * Recursively normalizes a single value with depth tracking.
	 *
	 * @param mixed $value The value to normalize.
	 * @param int   $depth The current recursion depth.
	 *
	 * @return ( $value is array ? array<array-key, mixed> : mixed|null )  The normalized value.
	 */
	private function normalize_value( mixed $value, int $depth ): mixed {
		if ( $depth > self::MAX_RECURSION_DEPTH ) {
			return sprintf( '[recursion limit reached after %d levels]', self::MAX_RECURSION_DEPTH );
		}

		if ( null === $value ) {
			return null;
		}

		if ( is_scalar( $value ) ) {
			return $this->normalize_scalar( $value );
		}

		if ( is_array( $value ) ) {
			return $this->normalize_array( $value, $depth );
		}

		if ( is_object( $value ) ) {
			return $this->normalize_object( $value, $depth );
		}

		if ( is_resource( $value ) ) {
			return sprintf( '[resource(%s)]', get_resource_type( $value ) );
		}

		return sprintf( '[unknown type: %s]', gettype( $value ) );
	}

	/**
	 * Normalizes a scalar value, converting non-finite floats to strings.
	 *
	 * @param bool|float|int|string $value The scalar value to normalize.
	 *
	 * @return bool|float|int|string The normalized scalar value.
	 */
	private function normalize_scalar( bool|float|int|string $value ): bool|float|int|string {
		if ( ! is_float( $value ) ) {
			return $value;
		}

		if ( is_infinite( $value ) ) {
			return ( 0 < $value ? '' : '-' ) . 'INF';
		}

		if ( is_nan( $value ) ) {
			return 'NaN';
		}

		return $value;
	}

	/**
	 * Normalizes an array, respecting item limits.
	 *
	 * @param array<array-key, mixed> $data The array to normalize.
	 * @param int                     $depth The current recursion depth.
	 *
	 * @return array<array-key, mixed> The normalized array.
	 */
	private function normalize_array( array $data, int $depth ): array {
		$normalized = [];
		$count      = 0;
		foreach ( $data as $key => $item ) {
			if ( $count >= self::MAX_ARRAY_ITEMS ) {
				$normalized['...'] = sprintf( '[array truncated; showing %d of %d items]', self::MAX_ARRAY_ITEMS, count( $data ) );
				break;
			}
			$normalized[ $key ] = $this->normalize_value( $item, $depth + 1 );
			++$count;
		}

		return $normalized;
	}

	/**
	 * Normalizes an object into a string or array representation.
	 *
	 * @param object $obj   The object to normalize.
	 * @param int    $depth The current recursion depth.
	 *
	 * @return mixed The normalized object representation.
	 */
	private function normalize_object( object $obj, int $depth ): mixed {
		if ( $obj instanceof Closure ) {
			return '[Closure]';
		}
		if ( $obj instanceof DateTimeImmutable ) {
			return $obj->format( 'Y-m-d\TH:i:s.uP' );
		}
		if ( $obj instanceof JsonSerializable ) {
			// Recursively normalize the result of jsonSerialize to handle nested complex objects.
			return $this->normalize_value( $obj->jsonSerialize(), $depth + 1 );
		}
		if ( $obj instanceof Throwable ) {
			return $this->normalize_exception( $obj, $depth );
		}
		// For __PHP_Incomplete_Class, just show the class name.
		if ( __PHP_Incomplete_Class::class === $obj::class ) {
			$accessor = new ArrayObject( $obj );

			return sprintf( '[Incomplete Class: %s]', (string) $accessor['__PHP_Incomplete_Class_Name'] );
		}
		// Check for __toString last, as it can be a heavy operation.
		if ( method_exists( $obj, '__toString' ) ) {
			try {
				return (string) $obj;
			} catch ( Throwable ) {
				// if __toString fails, fall back to the class name.
				return sprintf( '[object(%s) - __toString failed]', $obj::class );
			}
		}

		return sprintf( '[object(%s)]', $obj::class );
	}

	/**
	 * Normalizes a Throwable (Exception) into a structured array.
	 *
	 * @param Throwable $exception The exception to normalize.
	 * @param int       $depth The current recursion depth.
	 *
	 * @return array<string, mixed> The normalized exception data.
	 */
	private function normalize_exception( Throwable $exception, int $depth ): array {
		$data = [
			'class'   => $exception::class,
			'message' => $exception->getMessage(),
			'code'    => $exception->getCode(),
			'file'    => $exception->getFile() . ':' . $exception->getLine(),
		];

		$trace_frames  = explode( "\n", $exception->getTraceAsString() );
		$data['trace'] = $trace_frames;

		$previous = $exception->getPrevious();
		if ( $previous instanceof Throwable ) {
			$data['previous'] = $this->normalize_exception( $previous, $depth + 1 );
		}

		return $data;
	}
}
