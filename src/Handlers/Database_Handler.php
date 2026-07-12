<?php
/**
 * Log handler for writing to the WordPress database.
 *
 * @package WPTechnix\WP_Simple_Logger\Handlers
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Handlers;

use Psr\Log\LogLevel;
use WPTechnix\WP_Simple_Logger\Admin\Log_Viewer;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Handlers\Database\Database_Installer;
use WPTechnix\WP_Simple_Logger\Handlers\Database\Log_Repository;

/**
 * Class Database_Handler.
 *
 * A self-contained component that buffers log records and inserts them into a
 * custom, developer-defined database table.
 */
final class Database_Handler extends Abstract_Handler {

	/**
	 * Configuration for the admin log viewer.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $viewer_config = null;

	/**
	 * The repository for database operations for this specific handler instance.
	 *
	 * @var Log_Repository
	 */
	private Log_Repository $repository;

	/**
	 * The installer for this specific handler instance.
	 *
	 * @var Database_Installer
	 */
	private Database_Installer $installer;

	/**
	 * The time in seconds to keep logs before they are deleted. 0 means forever.
	 *
	 * @var int
	 */
	private int $expiry_seconds;

	/**
	 * Database_Handler constructor.
	 *
	 * @param string $table_name     The full, unique name for the database table.
	 * @param string $min_level      The minimum PSR-3 log level to handle.
	 * @param int    $buffer_limit   The number of records to buffer before flushing. 0 for no buffer limit.
	 * @param int    $expiry_seconds The number of seconds to keep logs. 0 to keep forever.
	 */
	public function __construct(
		string $table_name,
		string $min_level = LogLevel::DEBUG,
		int $buffer_limit = 20,
		int $expiry_seconds = 0
	) {
		parent::__construct( $min_level, $buffer_limit );
		$this->repository     = new Log_Repository( $table_name );
		$this->installer      = new Database_Installer( $table_name );
		$this->expiry_seconds = $expiry_seconds;
	}

	/**
	 * Registers WordPress hooks for this handler (installer, viewer, and cleanup).
	 */
	public function init_hooks(): void {
		add_action( 'init', [ $this->installer, 'install' ], 10 );
		add_action( 'init', [ $this, 'maybe_run_cleanup' ], 11 );

		if ( null === $this->viewer_config ) {
			return;
		}

		$viewer = new Log_Viewer( $this->viewer_config, $this->repository );
		$viewer->register_hooks();
	}

	/**
	 * Inserts the batch of buffered logs into the database.
	 *
	 * @param array<int, Log_Entry> $entries The buffered log entries to write.
	 */
	protected function write( array $entries ): void {
		$data_to_insert = array_map(
			static fn ( Log_Entry $entry ) => [
				'channel'   => $entry->get_channel(),
				'level'     => $entry->get_level_priority(),
				'message'   => $entry->get_message(),
				'context'   => $entry->get_context(),
				'timestamp' => $entry->get_date_time(),
			],
			$entries
		);

		$this->repository->insert_many( $data_to_insert );
	}

	/**
	 * Deletes expired logs, throttled to run once per hour to avoid performance issues.
	 */
	public function maybe_run_cleanup(): void {
		if ( 0 >= $this->expiry_seconds ) {
			return;
		}

		$transient_key = 'wpsl_cleanup_lock_' . md5( $this->installer->get_table_name() );

		// The transient acts as a lock to prevent this expensive query from running on every page load.
		if ( false !== get_transient( $transient_key ) ) {
			return;
		}

		$this->repository->delete_expired_logs( $this->expiry_seconds );
		// Set the lock, which will auto-expire after one hour.
		set_transient( $transient_key, '1', 60 * 60 );
	}

	/**
	 * Enables and configures the admin log viewer page.
	 *
	 * @param string $parent_menu_slug The slug of the parent menu.
	 * @param string $page_slug        A unique slug for the log viewer page.
	 * @param string $page_title       The title displayed on the viewer page.
	 * @param string $menu_title       The text for the link in the admin menu.
	 * @param string $capability       The capability required to view the page.
	 */
	public function set_admin_viewer(
		string $parent_menu_slug,
		string $page_slug,
		string $page_title = 'Application Logs',
		string $menu_title = 'Logs',
		string $capability = 'manage_options'
	): self {
		$this->viewer_config = [
			'parent_slug' => $parent_menu_slug,
			'page_slug'   => $page_slug,
			'page_title'  => $page_title,
			'menu_title'  => $menu_title,
			'capability'  => $capability,
		];
		return $this;
	}
}
