<?php
/**
 * Interface for log context normalizers.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Contracts;

/**
 * Interface Normalizer_Interface.
 *
 * Defines the contract for classes that convert a raw log context array into
 * a safe, serializable representation (scalars, arrays, and normalized
 * objects/exceptions only).
 */
interface Normalizer_Interface {

	/**
	 * Normalizes a log context array.
	 *
	 * @param array<array-key, mixed> $data The raw context data.
	 *
	 * @return array<array-key, mixed> The normalized data.
	 */
	public function normalize_context( array $data ): array;
}
