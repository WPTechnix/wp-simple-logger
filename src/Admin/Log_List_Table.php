<?php
/**
 * Renders the WP_List_Table for displaying logs.
 *
 * @package WPTechnix\WP_Simple_Logger\Admin
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Admin;

use WPTechnix\WP_Simple_Logger\Handlers\Database\Log_Repository;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Log_Level;
use WPTechnix\WP_Simple_Logger\Utils\Color;
use WP_List_Table;
use Override;

if ( ! class_exists( '\WP_List_Table' ) && file_exists( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Log_List_Table.
 *
 * Creates a sortable, filterable, and paginated table of log entries.
 */
final class Log_List_Table extends WP_List_Table {

	/**
	 * The repository for database operations.
	 *
	 * @var Log_Repository
	 */
	private Log_Repository $repository;

	/**
	 * A cached list of distinct channels.
	 *
	 * @var array<string>|null
	 */
	private ?array $channels = null;

	/**
	 * Cached filters for the current request.
	 *
	 * @var array{
	 *   channel: string,
	 *   level: string,
	 *   level_compare: 'eq'|'min'|'max',
	 *   search: string,
	 *   orderby: 'id'|'timestamp'|'channel'|'level',
	 *   order: 'ASC'|'DESC'
	 * }|null
	 */
	private ?array $filters = null;

	/**
	 * Log_List_Table constructor.
	 *
	 * @param Log_Repository $repository The repository for database access.
	 */
	public function __construct( Log_Repository $repository ) {
		parent::__construct(
			[
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			]
		);
		$this->items      = [];
		$this->repository = $repository;
	}

	/**
	 * Checks if there are any items to display.
	 *
	 * @return bool True if there are items, false otherwise.
	 */
	#[Override]
	public function has_items(): bool {
		return 0 !== count( $this->items );
	}

	/**
	 * Prepares the list of items for display.
	 */
	#[Override]
	public function prepare_items(): void {
		$per_page     = 30;
		$current_page = $this->get_pagenum();
		$filters      = $this->get_filters_from_request();

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];

		$total_items = $this->repository->get_total_logs_count( $filters );

		$get_logs_filters = $filters;
		$this->items      = $this->repository->get_logs( $get_logs_filters, $per_page, $current_page );

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			]
		);
	}

	/**
	 * Defines the columns that are going to be used in the table.
	 *
	 * @return array<string, string>
	 */
	#[Override]
	public function get_columns(): array {
		return [
			'cb'        => '<input type="checkbox" />',
			'channel'   => esc_html__( 'Channel', 'wp-simple-logger' ),
			'level'     => esc_html__( 'Level', 'wp-simple-logger' ),
			'message'   => esc_html__( 'Message', 'wp-simple-logger' ),
			'timestamp' => esc_html__( 'Timestamp', 'wp-simple-logger' ),
		];
	}

	/**
	 * Handles the primary column display with row actions.
	 *
	 * @param object $item The log item.
	 */
	public function column_channel( object $item ): string {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @var Log_Entry $entry */
		$entry = $item;
		// The `row_actions` call is a WP_List_Table convention for the primary column.
		return '<strong>' . esc_html( $entry->get_channel() ) . '</strong>' . $this->row_actions( [] );
	}

	/**
	 * Renders the content for all non-primary columns.
	 *
	 * @param array<array-key,mixed>|object $item        The log item data.
	 * @param string                        $column_name The name of the column.
	 *
	 * @return string The column content.
	 */
	#[Override]
	public function column_default( $item, $column_name ): string {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @var Log_Entry $entry */
		$entry       = $item;
		$level_color = Log_Level::get_level_color( $entry->get_level_priority() );
		$level_style = 'display: inline-block; padding: 2px 8px; border-radius: 3px; background-color:' . esc_attr( $level_color ) . '; color: ' . ( Color::is_color_dark( $level_color ) ? '#fff' : '#000' ) . ';';

		return match ( $column_name ) {
			'level' => '<span style="' . $level_style . '">' . esc_html( strtoupper( $entry->get_level_name() ) ) . '</span>',
			'message' => $this->render_message_column( $entry ),
			'timestamp' => $this->render_timestamp_column( $entry ),
			default => '',
		};
	}

	/**
	 * Renders the content for the 'timestamp' column with better formatting.
	 *
	 * @param Log_Entry $item The log item.
	 * @return string The column HTML.
	 */
	private function render_timestamp_column( Log_Entry $item ): string {
		$date_string = $item->get_formatted_date_time( 'Y-m-d' );
		$time_string = $item->get_formatted_date_time( 'H:i:s' );
		return esc_html( $date_string ) . '<br><small class="wpsl-time">' . esc_html( $time_string ) . '</small>';
	}

	/**
	 * Renders the content for the 'message' column, including the enhanced context viewer.
	 *
	 * @param Log_Entry $item The log item.
	 * @return string The column HTML.
	 */
	private function render_message_column( Log_Entry $item ): string {
		$message = esc_html( $item->get_message() );
		$context = $item->get_context();

		if ( null !== $context ) {
			$context_json = wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			$viewer_html  = '<div class="wpsl-context-viewer" style="display: none;">';
			$viewer_html .= '<div class="wpsl-context-header">';
			$viewer_html .= '<span>' . esc_html__( 'Context Data', 'wp-simple-logger' ) . '</span>';
			$viewer_html .= '<button type="button" class="button-link wpsl-copy-context">' . esc_html__( 'Copy', 'wp-simple-logger' ) . '</button>';
			$viewer_html .= '</div>';
			$viewer_html .= '<pre class="wpsl-context-pre">' . esc_html( (string) $context_json ) . '</pre>';
			$viewer_html .= '</div>';

			$toggle_button = sprintf(
				'<button type="button" class="button-link wpsl-context-toggle" aria-expanded="false">%s</button>',
				esc_html__( 'View Context', 'wp-simple-logger' )
			);

			$message .= ' ' . $toggle_button . $viewer_html;
		}
		return $message;
	}

	/**
	 * Defines the sortable columns for the table.
	 *
	 * @return array<string, array<string|bool>>
	 */
	#[Override]
	public function get_sortable_columns(): array {
		return [
			'timestamp' => [ 'timestamp', false ],
			'channel'   => [ 'channel', false ],
			'level'     => [ 'level', false ],
		];
	}

	/**
	 * Safely gets and sanitizes filter values from the request, caching the result.
	 *
	 * @return array{
	 *     channel: string,
	 *     level: string,
	 *     level_compare: 'eq'|'min'|'max',
	 *     search: string,
	 *     orderby: 'id'|'timestamp'|'channel'|'level',
	 *     order: 'ASC'|'DESC'
	 * } The sanitized and cached filters.
	 */
	public function get_filters_from_request(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( null !== $this->filters ) {
			return $this->filters;
		}

		$this->filters = [
			'channel'       => $this->get_request_text( 'channel' ),
			'level'         => $this->get_request_text( 'level' ),
			'level_compare' => isset( $_GET['level_compare'] ) && in_array( $_GET['level_compare'], [ 'eq', 'min', 'max' ], true ) ? $_GET['level_compare'] : 'eq',
			'search'        => $this->get_request_text( 's' ),
			'orderby'       => isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], [ 'id', 'timestamp', 'channel', 'level' ], true ) ? $_GET['orderby'] : 'id',
			'order'         => isset( $_GET['order'] ) && in_array( $_GET['order'], [ 'asc', 'ASC', 'desc', 'DESC' ], true ) ? strtoupper( $_GET['order'] ) : 'DESC',
		];

		return $this->filters;
        // phpcs:enable
	}

	/**
	 * Reads a request value as a sanitized string, defaulting to an empty string.
	 *
	 * @param string $key The `$_GET` key to read.
	 *
	 * @return string The sanitized value, or an empty string when absent.
	 */
	private function get_request_text( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ $key ] ) || ! is_string( $_GET[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
	}

	/**
	 * Defines the bulk actions.
	 *
	 * @return array<string, string>
	 */
	#[Override]
	public function get_bulk_actions(): array {
		return [ 'delete' => esc_html__( 'Delete', 'wp-simple-logger' ) ];
	}

	/**
	 * Renders the checkbox for bulk actions.
	 *
	 * @param array<array-key,mixed>|object $item The current log entry item.
	 *
	 * @return string The checkbox HTML.
	 */
	#[Override]
	public function column_cb( $item ): string {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @var Log_Entry $entry */
		$entry = $item;
		return sprintf( '<input type="checkbox" name="log_ids[]" value="%s" />', esc_attr( (string) $entry->get_id() ) );
	}

	/**
	 * Displays the table navigation (filters, bulk actions).
	 *
	 * @param string $which The location of the navigation ('top' or 'bottom').
	 */
	#[Override]
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<?php
			$this->render_channel_filter();
			$this->render_level_filter();
			submit_button( esc_html__( 'Filter', 'wp-simple-logger' ), 'secondary', 'filter_action', false );
			$this->render_clear_logs_button();
			?>
		</div>
		<?php
	}

	/**
	 * Renders the channel filter dropdown.
	 */
	private function render_channel_filter(): void {
		if ( null === $this->channels ) {
			$this->channels = $this->repository->get_distinct_channels();
		}
		if ( 0 === count( $this->channels ) ) {
			return;
		}
		$current_channel = $this->get_filters_from_request()['channel'];
		?>
		<label for="filter-by-channel" class="screen-reader-text"><?php esc_html_e( 'Filter by channel', 'wp-simple-logger' ); ?></label>
		<select name="channel" id="filter-by-channel">
			<option value=""><?php esc_html_e( 'All Channels', 'wp-simple-logger' ); ?></option>
			<?php foreach ( $this->channels as $channel ) : ?>
				<option value="<?php echo esc_attr( $channel ); ?>" <?php selected( $current_channel, $channel ); ?>>
					<?php echo esc_html( $channel ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Renders the log level filter dropdowns.
	 */
	private function render_level_filter(): void {
		$levels          = Log_Level::get_all_levels();
		$filters         = $this->get_filters_from_request();
		$current_level   = $filters['level'];
		$current_compare = $filters['level_compare'];
		?>
		<label for="filter-by-level-compare" class="screen-reader-text"><?php esc_html_e( 'Filter by level comparison', 'wp-simple-logger' ); ?></label>
		<select name="level_compare" id="filter-by-level-compare">
			<option value="eq" <?php selected( $current_compare, 'eq' ); ?>><?php esc_html_e( 'Level Is', 'wp-simple-logger' ); ?></option>
			<option value="min" <?php selected( $current_compare, 'min' ); ?>><?php esc_html_e( 'Minimum Level', 'wp-simple-logger' ); ?></option>
			<option value="max" <?php selected( $current_compare, 'max' ); ?>><?php esc_html_e( 'Maximum Level', 'wp-simple-logger' ); ?></option>
		</select>

		<label for="filter-by-level" class="screen-reader-text"><?php esc_html_e( 'Filter by level', 'wp-simple-logger' ); ?></label>
		<select name="level" id="filter-by-level">
			<option value=""><?php esc_html_e( 'All Levels', 'wp-simple-logger' ); ?></option>
			<?php foreach ( $levels as $level ) : ?>
				<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $current_level, $level ); ?>>
					<?php echo esc_html( strtoupper( $level ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Renders the 'Clear Logs' button with a data attribute for the JS confirmation.
	 */
	private function render_clear_logs_button(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page    = isset( $_REQUEST['page'] ) && is_string( $_REQUEST['page'] ) ? sanitize_key( $_REQUEST['page'] ) : '';
		$channel = $this->get_filters_from_request()['channel'];

		$button_text  = '' !== $channel ? __( 'Clear Channel Logs', 'wp-simple-logger' ) : __( 'Clear All Logs', 'wp-simple-logger' );
		$confirm_text = '' !== $channel ?
			/* translators: %s: the channel name */
			sprintf( esc_html__( 'Are you sure you want to permanently delete all logs from the "%s" channel?', 'wp-simple-logger' ), $channel ) :
			esc_html__( 'Are you sure you want to permanently delete all logs?', 'wp-simple-logger' );

		$clear_url = wp_nonce_url(
			add_query_arg(
				[
					'page'    => $page,
					'action'  => 'clear_logs',
					'channel' => $channel,
				],
				admin_url( 'admin.php' )
			),
			'wpsl_clear_logs_nonce'
		);
		?>
		<a href="<?php echo esc_url( $clear_url ); ?>" class="button button-danger wpsl-clear-logs-button" style="margin-left: 5px;" data-confirm-text="<?php echo esc_attr( $confirm_text ); ?>">
			<?php echo esc_html( $button_text ); ?>
		</a>
		<?php
        // phpcs:enable
	}
}
