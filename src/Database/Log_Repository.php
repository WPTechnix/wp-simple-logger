<?php
/**
 * Handles all database interactions for a specific log table.
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Database;

use wpdb;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Log_Level;
use WPTechnix\WP_Simple_Logger\Utils\Json_Encoder;

/**
 * Class Log_Repository.
 *
 * Provides a clean API for all CRUD operations on a specific log table,
 * returning strongly-typed Log_Entry objects.
 *
 * @phpstan-type Log_DB_Row array{
 *    id: numeric-string,
 *    channel: string,
 *    level: numeric-string,
 *    message: string,
 *    context: string|null,
 *    timestamp: string
 * }
 *
 * @phpstan-type Log_Filter_Args array{
 *    channel?: string,
 *    level?: string,
 *    level_compare?: 'eq'|'min'|'max',
 *    search?: string,
 *    orderby?: 'id'|'channel'|'level'|'timestamp',
 *    order?: 'ASC'|'DESC'
 * }
 */
final class Log_Repository {

	/**
	 * WordPress database object.
	 */
	private wpdb $wpdb;

	/**
	 * The name of the log table this repository manages.
	 */
	private string $table_name;

	/**
	 * Database_Repository constructor.
	 *
	 * @param string $table_name The full name of the log table.
	 *
	 * @throws \RuntimeException If the WordPress database object is unavailable.
	 */
	public function __construct( string $table_name ) {
		$this->table_name = Table_Name_Validator::validate( $table_name );

		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			throw new \RuntimeException( 'The global $wpdb database object is not available.' );
		}
		$this->wpdb = $wpdb;
	}

	/**
	 * Inserts multiple log records into the database in a single query.
	 *
	 * @param array<int, array<string, mixed>> $logs An array of log records.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function insert_many( array $logs ): bool {
		if ( 0 === count( $logs ) ) {
			return false;
		}

		$values        = [];
		$placeholders  = [];
		$query_columns = [ 'channel', 'level', 'message', 'context', 'timestamp' ];
		$formats       = [ '%s', '%d', '%s', '%s', '%s' ];

		foreach ( $logs as $log ) {
			$row_values = [];
			foreach ( $query_columns as $column ) {
				$value = $log[ $column ] ?? null;
				if ( 'context' === $column && is_array( $value ) ) {
					$value = Json_Encoder::encode( $value );
				}
				$row_values[] = $value;
			}
			$values         = array_merge( $values, $row_values );
			$placeholders[] = '(' . implode( ', ', $formats ) . ')';
		}

		$columns_sql = '`' . implode( '`, `', $query_columns ) . '`';

		// @phpstan-ignore-next-line
		$result = $this->wpdb->query( (string) $this->wpdb->prepare( "INSERT INTO {$this->table_name} ($columns_sql) VALUES " . implode( ', ', $placeholders ), array_values( $values ) ) ); // phpcs:ignore WordPress.DB

		return false !== $result;
	}

	/**
	 * Retrieves a paginated and filtered list of logs.
	 *
	 * @param array $filters  Filtering and sorting criteria.
	 * @param int   $per_page Number of items per page.
	 * @param int   $page     The current page number.
	 *
	 * @phpstan-param Log_Filter_Args $filters
	 *
	 * @return list<Log_Entry> The list of log entry objects.
	 */
	public function get_logs( array $filters, int $per_page, int $page ): array {
		[$where_sql, $params] = $this->build_where_clause( $filters );
		$offset               = ( $page - 1 ) * $per_page;

		$sort_args = wp_parse_args(
			$filters,
			[
				'orderby' => 'id',
				'order'   => 'DESC',
			]
		);

		// Whitelist of sortable columns to prevent SQL injection.
		$sortable_columns = [ 'id', 'channel', 'level', 'timestamp' ];
		$orderby          = in_array( $sort_args['orderby'], $sortable_columns, true ) ? $sort_args['orderby'] : 'id';
		$order            = is_string( $sort_args['order'] ) && 'ASC' === strtoupper( $sort_args['order'] ) ? 'ASC' : 'DESC';
		$order_by_sql     = "ORDER BY {$orderby} {$order}";

		$query = "SELECT * FROM {$this->table_name} WHERE {$where_sql} {$order_by_sql} LIMIT %d, %d";

		$final_params = array_merge( $params, [ $offset, $per_page ] );

		// @phpstan-ignore-next-line
		$results = $this->wpdb->get_results( (string) $this->wpdb->prepare( $query, $final_params ), \ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL,WordPress.DB.PreparedSQL

		if ( ! is_array( $results ) ) {
			return [];
		}

		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @var list<Log_DB_Row> $rows */
		$rows = $results;

		return array_map( [ $this, 'db_row_to_log_entry' ], $rows );
	}

	/**
	 * Gets the total count of logs matching a set of filters.
	 *
	 * @param array $filters Filtering criteria.
	 *
	 * @phpstan-param Log_Filter_Args $filters
	 *
	 * @return int The total number of matching logs.
	 */
	public function get_total_logs_count( array $filters ): int {
		[$where_sql, $params] = $this->build_where_clause( $filters );
		$query                = "SELECT COUNT(id) FROM {$this->table_name} WHERE {$where_sql}";

		if ( 0 === count( $params ) ) {
			$count = $this->wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL
		} else {
			// @phpstan-ignore-next-line
			$count = $this->wpdb->get_var( (string) $this->wpdb->prepare( $query, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		return (int) $count;
	}

	/**
	 * Builds the WHERE clause and parameter array for a query based on filters.
	 *
	 * @param array $filters Filtering criteria.
	 *
	 * @phpstan-param Log_Filter_Args $filters
	 *
	 * @return array{0: string, 1: list<string|int>} The WHERE SQL and the parameters array.
	 */
	private function build_where_clause( array $filters ): array {
		$where_clauses = [ '1=1' ];
		$params        = [];

		if ( isset( $filters['channel'] ) && '' !== $filters['channel'] ) {
			$where_clauses[] = 'channel = %s';
			$params[]        = $filters['channel'];
		}

		if ( isset( $filters['level'] ) && '' !== $filters['level'] ) {
			$level_priority = Log_Level::get_level_priority( $filters['level'] );
			$compare        = $filters['level_compare'] ?? 'eq';
			$operator       = '=';
			if ( 'min' === $compare ) {
				$operator = '>=';
			} elseif ( 'max' === $compare ) {
				$operator = '<=';
			}
			$where_clauses[] = "level {$operator} %d";
			$params[]        = $level_priority;
		}

		if ( isset( $filters['search'] ) && '' !== $filters['search'] ) {
			$search_term     = '%' . $this->wpdb->esc_like( $filters['search'] ) . '%';
			$where_clauses[] = '(message LIKE %s OR context LIKE %s)';
			$params[]        = $search_term;
			$params[]        = $search_term;
		}

		return [ implode( ' AND ', $where_clauses ), $params ];
	}

	/**
	 * Retrieves a list of all unique channel names present in the logs.
	 *
	 * @return list<string> An array of channel names.
	 */
	public function get_distinct_channels(): array {
		$results = $this->wpdb->get_col( "SELECT DISTINCT channel FROM {$this->table_name} ORDER BY channel ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( ! is_array( $results ) ) {
			return [];
		}

		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @var array<int, string> $channels */
		$channels = $results;

		return array_values( $channels );
	}

	/**
	 * Deletes logs by their primary IDs.
	 *
	 * @param array<int> $ids An array of log IDs to delete.
	 *
	 * @return int The number of rows deleted.
	 */
	public function delete_by_ids( array $ids ): int {
		if ( 0 === count( $ids ) ) {
			return 0;
		}

		$id_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// @phpstan-ignore-next-line
		$result = $this->wpdb->query( (string) $this->wpdb->prepare( "DELETE FROM {$this->table_name} WHERE id IN ({$id_placeholders})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL,WordPress.DB.PreparedSQLPlaceholders

		return is_int( $result ) ? $result : 0;
	}

	/**
	 * Deletes all records from the log table, optionally filtered by channel.
	 *
	 * @param string $channel The log channel to clear (Optional).
	 *                        If not provided, all logs will be cleared.
	 */
	public function clear( string $channel = '' ): void {
		if ( '' !== $channel ) {
			// @phpstan-ignore-next-line
			$this->wpdb->query( (string) $this->wpdb->prepare( "DELETE FROM {$this->table_name} WHERE channel = %s", $channel ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		} else {
			$this->wpdb->query( "TRUNCATE TABLE {$this->table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
	}

	/**
	 * Deletes logs older than a specified time.
	 *
	 * @param int $seconds The age in seconds.
	 */
	public function delete_expired_logs( int $seconds ): void {
		// @phpstan-ignore-next-line
		$this->wpdb->query( (string) $this->wpdb->prepare( "DELETE FROM {$this->table_name} WHERE timestamp < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d SECOND)", $seconds ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * A helper method to convert a database row to a Log_Entry object.
	 *
	 * @param array $row The database row to convert.
	 *
	 * @phpstan-param Log_DB_Row $row
	 *
	 * @return Log_Entry The converted log entry object.
	 */
	private function db_row_to_log_entry( array $row ): Log_Entry {
		$id             = (int) $row['id'];
		$channel        = $row['channel'];
		$level_priority = (int) $row['level'];
		$message        = $row['message'];
		$date_time      = $row['timestamp'];

		$context = null;
		if ( isset( $row['context'] ) ) {
			$decoded_context = json_decode( $row['context'], true );
			if ( is_array( $decoded_context ) ) {
				$context = $decoded_context;
			}
		}

		return new Log_Entry( $channel, $level_priority, $message, $date_time, $context, $id );
	}
}
