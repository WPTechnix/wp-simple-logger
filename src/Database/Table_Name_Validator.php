<?php
/**
 * Validates custom database table names used by the logging library.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Database;

use InvalidArgumentException;

/**
 * Class Table_Name_Validator.
 *
 * Ensures a developer-supplied table name is safe to interpolate directly
 * into SQL statements, shared by `Log_Repository` and `Database_Installer`
 * so both enforce the exact same rule.
 */
final class Table_Name_Validator {

	/**
	 * Validates a table name, throwing if it contains anything unsafe.
	 *
	 * @param string $table_name The table name to validate.
	 *
	 * @return string The validated table name, unchanged.
	 *
	 * @throws InvalidArgumentException If the table name contains characters
	 *                                  other than letters, numbers, and underscores.
	 */
	public static function validate( string $table_name ): string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $table_name ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Invalid log table name: %s. Only letters, numbers, and underscores are allowed.', $table_name )
			);
		}

		return $table_name;
	}
}
