<?php
/**
 * Normalizes log context data into a safe, serializable format.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Normalizers;

use DateTimeInterface;
use JsonSerializable;
use Throwable;
use __PHP_Incomplete_Class;
use ArrayObject;
use WPTechnix\WP_Simple_Logger\Contracts\Normalizer_Interface;
use WPTechnix\WP_Simple_Logger\Utils\Json_Encoder;

/**
 * Class Data_Normalizer.
 *
 * Converts complex data types like objects and exceptions in a log context
 * array into a scalar or array representation suitable for serialization.
 * This intentionally mirrors Monolog's `NormalizerFormatter` normalization
 * algorithm (dispatch order, cutoff wording, object/exception shapes) so
 * behavior is predictable to anyone familiar with Monolog.
 */
final class Data_Normalizer implements Normalizer_Interface {

	/**
	 * The default maximum number of levels to recurse into a data structure.
	 */
	public const MAX_NORMALIZE_DEPTH = 9;

	/**
	 * The default maximum number of items to normalize in a single array.
	 */
	public const MAX_NORMALIZE_ITEM_COUNT = 1_000;

	/**
	 * The default date format used for DateTimeInterface values (Monolog's SIMPLE_DATE).
	 */
	public const DEFAULT_DATE_FORMAT = 'Y-m-d\TH:i:sP';

	/**
	 * Data_Normalizer constructor.
	 *
	 * @param int    $max_depth      The maximum recursion depth before aborting normalization.
	 * @param int    $max_item_count The maximum number of array items to normalize before aborting.
	 * @param string $date_format    The PHP date format used to normalize DateTimeInterface values.
	 */
	public function __construct(
		private int $max_depth = self::MAX_NORMALIZE_DEPTH,
		private int $max_item_count = self::MAX_NORMALIZE_ITEM_COUNT,
		private string $date_format = self::DEFAULT_DATE_FORMAT
	) {}

	/**
	 * Normalizes a log context array.
	 *
	 * @param array<array-key, mixed> $data The raw context data.
	 *
	 * @return array<array-key, mixed> The normalized data.
	 */
	public function normalize_context( array $data ): array {
		return $this->normalize( $data, 0 );
	}

	/**
	 * Recursively normalizes a single value with depth tracking.
	 *
	 * @param mixed $value The value to normalize.
	 * @param int   $depth The current recursion depth.
	 *
	 * @return ( $value is array ? array<array-key, mixed> : mixed )  The normalized value.
	 */
	private function normalize( mixed $value, int $depth ): mixed {
		if ( $depth > $this->max_depth ) {
			return sprintf( 'Over %d levels deep, aborting normalization', $this->max_depth );
		}

		if ( null === $value || is_scalar( $value ) ) {
			return $this->normalize_scalar( $value );
		}

		if ( is_array( $value ) ) {
			return $this->normalize_array( $value, $depth );
		}

		if ( $value instanceof DateTimeInterface ) {
			return $value->format( $this->date_format );
		}

		if ( is_object( $value ) ) {
			return $this->normalize_object( $value, $depth );
		}

		if ( is_resource( $value ) ) {
			return sprintf( '[resource(%s)]', get_resource_type( $value ) );
		}

		return sprintf( '[unknown(%s)]', gettype( $value ) );
	}

	/**
	 * Normalizes a scalar value, converting non-finite floats to strings.
	 *
	 * @param bool|float|int|string|null $value The scalar value to normalize.
	 *
	 * @return bool|float|int|string|null The normalized scalar value.
	 */
	private function normalize_scalar( bool|float|int|string|null $value ): bool|float|int|string|null {
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
	 * @param array<array-key, mixed> $data  The array to normalize.
	 * @param int                     $depth The current recursion depth.
	 *
	 * @return array<array-key, mixed> The normalized array.
	 */
	private function normalize_array( array $data, int $depth ): array {
		$normalized = [];
		$count      = 0;
		foreach ( $data as $key => $item ) {
			if ( $count >= $this->max_item_count ) {
				$normalized['...'] = sprintf(
					'Over %d items (%d total), aborting normalization',
					$this->max_item_count,
					count( $data )
				);
				break;
			}
			$normalized[ $key ] = $this->normalize( $item, $depth + 1 );
			++$count;
		}

		return $normalized;
	}

	/**
	 * Normalizes an object into a class-keyed array, or an exception structure.
	 *
	 * @param object $obj   The object to normalize.
	 * @param int    $depth The current recursion depth.
	 *
	 * @return mixed The normalized object representation.
	 */
	private function normalize_object( object $obj, int $depth ): mixed {
		if ( $obj instanceof Throwable ) {
			return $this->normalize_exception( $obj, $depth );
		}

		if ( $obj instanceof JsonSerializable ) {
			$value = $this->normalize( $obj->jsonSerialize(), $depth + 1 );

			return [ $this->get_class_name( $obj ) => $value ];
		}

		// For __PHP_Incomplete_Class, surface the original class name it was recorded under.
		if ( __PHP_Incomplete_Class::class === $obj::class ) {
			$accessor = new ArrayObject( $obj );
			$value    = isset( $accessor['__PHP_Incomplete_Class_Name'] ) && is_string( $accessor['__PHP_Incomplete_Class_Name'] )
				? $accessor['__PHP_Incomplete_Class_Name']
				: null;

			return [ $this->get_class_name( $obj ) => $value ];
		}

		// Check for __toString last, as it can be a heavy operation.
		if ( method_exists( $obj, '__toString' ) ) {
			try {
				return [ $this->get_class_name( $obj ) => (string) $obj ];
			} catch ( Throwable ) {
				// If __toString fails, fall back to the same JSON round-trip as any other object.
				return [ $this->get_class_name( $obj ) => $this->json_roundtrip( $obj ) ];
			}
		}

		return [ $this->get_class_name( $obj ) => $this->json_roundtrip( $obj ) ];
	}

	/**
	 * Round-trips an object through JSON encoding to capture its public properties.
	 *
	 * @param object $obj The object to convert.
	 *
	 * @return mixed The decoded value, or null if the object could not be encoded.
	 */
	private function json_roundtrip( object $obj ): mixed {
		$json = Json_Encoder::encode( $obj );

		return null !== $json ? json_decode( $json, true ) : null;
	}

	/**
	 * Resolves a stable class name for an object, collapsing anonymous class names.
	 *
	 * @param object $obj The object to resolve a class name for.
	 *
	 * @return string The resolved class name.
	 */
	private function get_class_name( object $obj ): string {
		$class = $obj::class;

		if ( ! str_contains( $class, '@anonymous' ) ) {
			return $class;
		}

		$parent = get_parent_class( $obj );
		if ( false !== $parent ) {
			return $parent . '@anonymous';
		}

		$interfaces = class_implements( $obj );
		if ( false !== $interfaces && [] !== $interfaces ) {
			return ( (string) current( $interfaces ) ) . '@anonymous';
		}

		return 'class@anonymous';
	}

	/**
	 * Normalizes a Throwable (Exception) into a structured array.
	 *
	 * @param Throwable $exception The exception to normalize.
	 * @param int       $depth     The current recursion depth.
	 *
	 * @return array<int|string, mixed> The normalized exception data.
	 */
	private function normalize_exception( Throwable $exception, int $depth ): array {
		if ( $depth > $this->max_depth ) {
			return [ sprintf( 'Over %d levels deep, aborting normalization', $this->max_depth ) ];
		}

		$data = [
			'class'   => $this->get_class_name( $exception ),
			'message' => $exception->getMessage(),
			'code'    => (int) $exception->getCode(),
			'file'    => $exception->getFile() . ':' . $exception->getLine(),
		];

		$trace = [];
		foreach ( $exception->getTrace() as $frame ) {
			if ( isset( $frame['file'] ) && isset( $frame['line'] ) ) {
				$trace[] = $frame['file'] . ':' . $frame['line'];
			}
		}
		$data['trace'] = $trace;

		$previous = $exception->getPrevious();
		if ( $previous instanceof Throwable ) {
			$data['previous'] = $this->normalize_exception( $previous, $depth + 1 );
		}

		return $data;
	}
}
