<?php
/**
 * Handles database table creation and updates for a specific log table.
 *
 * @package WPTechnix\WP_Simple_Logger\Handlers\Database
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Handlers\Database;

/**
 * Class Database_Installer.
 *
 * Manages the schema for a single, specific log table to ensure portability and
 * prevent conflicts between different instances of the logger.
 */
final class Database_Installer {

	/**
	 * The current database schema version. Increment on schema changes.
	 */
	private const DB_VERSION = 100_001;

	/**
	 * The full name of the custom log table.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * The unique option key for storing this table's db version.
	 *
	 * @var string
	 */
	private string $option_key;

	/**
	 * Database_Installer constructor.
	 *
	 * @param string $table_name The full, unique name of the database table.
	 */
	public function __construct( string $table_name ) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $table_name ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Invalid log table name: %s. Only letters, numbers, and underscores are allowed.', $table_name )
			);
		}

		$this->table_name = $table_name;
		// Create a unique option key based on the table name to prevent conflicts.
		$this->option_key = 'wpsl_db_version_' . md5( $this->table_name );
	}

	/**
	 * Checks the database version and runs schema updates if needed.
	 */
	public function install(): void {
		$installed_version = get_option( $this->option_key, 0 );
		$installed_version = is_numeric( $installed_version ) ? (int) $installed_version : 0;

		if ( $installed_version >= self::DB_VERSION ) {
			return;
		}

		$this->run_schema_update();
		update_option( $this->option_key, self::DB_VERSION );
	}

	/**
	 * Gets the full name of the custom logs table.
	 *
	 * @return string The logs table name.
	 */
	public function get_table_name(): string {
		return $this->table_name;
	}

	/**
	 * Executes the database schema creation/update using dbDelta.
	 */
	private function run_schema_update(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$this->table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			channel VARCHAR(191) NOT NULL,
			level SMALLINT(4) NOT NULL,
			message TEXT NOT NULL,
			context LONGTEXT,
			timestamp DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY channel (channel),
			KEY level (level),
			KEY timestamp (timestamp)
		) {$charset_collate};";

		if ( ! file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			throw new \RuntimeException( 'WordPress upgrade.php is missing. Cannot run database schema update.' );
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );
	}
}
