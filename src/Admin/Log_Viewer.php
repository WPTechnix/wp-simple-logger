<?php
/**
 * Registers and renders the admin log viewer page.
 *
 * @package WPTechnix\WP_Simple_Logger\Admin
 */

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Admin;

use WPTechnix\WP_Simple_Logger\Handlers\Database\Log_Repository;
use WP_Screen;

/**
 * Class Log_Viewer.
 *
 * Handles the creation of the admin menu page, processing of actions,
 * and injection of necessary CSS/JS assets.
 */
final class Log_Viewer {

	/**
	 * Configuration for the admin page.
	 *
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * The repository for database operations.
	 *
	 * @var Log_Repository
	 */
	private Log_Repository $repository;

	/**
	 * The list table instance.
	 *
	 * @var Log_List_Table
	 */
	private Log_List_Table $list_table;

	/**
	 * The screen ID for the log viewer page.
	 *
	 * @var string
	 */
	private string $screen_id = '';

	/**
	 * Log_Viewer constructor.
	 *
	 * @param array<string, mixed> $config     Configuration for the admin page.
	 * @param Log_Repository       $repository The repository for database access.
	 */
	public function __construct( array $config, Log_Repository $repository ) {
		$this->config     = $config;
		$this->repository = $repository;
	}

	/**
	 * Get log list table instance.
	 */
	public function get_list_table(): Log_List_Table {
		return $this->list_table ??= new Log_List_Table( $this->repository );
	}

	/**
	 * Registers the necessary WordPress hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu_page' ], 10 );
		add_action( 'admin_init', [ $this, 'process_actions' ], 11 );
		add_action( 'admin_notices', [ $this, 'display_admin_notices' ] );
	}

	/**
	 * Displays admin notices for successful or failed actions using transients.
	 */
	public function display_admin_notices(): void {
		$screen = get_current_screen();
		if ( ! ( $screen instanceof WP_Screen ) || $screen->id !== $this->screen_id ) {
			return;
		}

		$transient_key = 'wpsl_admin_notice_' . get_current_user_id();
		$notice_html   = get_transient( $transient_key );

		if ( false === $notice_html || ! is_string( $notice_html ) ) {
			return;
		}

		// Delete the transient immediately to ensure it's only shown once.
		delete_transient( $transient_key );
		// The message is already prepared and escaped, so we can output it.
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', wp_kses_post( $notice_html ) );
	}

	/**
	 * Adds the submenu page to the WordPress admin menu and hooks asset injection.
	 */
	public function add_admin_menu_page(): void {
		$hook = add_submenu_page(
			(string) $this->config['parent_slug'],
			(string) $this->config['page_title'],
			(string) $this->config['menu_title'],
			(string) $this->config['capability'],
			(string) $this->config['page_slug'],
			[ $this, 'render_page' ]
		);

		if ( ! is_string( $hook ) || '' === $hook ) {
			return;
		}

		$this->screen_id = $hook;
		add_action( 'admin_footer-' . $this->screen_id, [ $this, 'inline_page_assets' ], 10 );
	}

	/**
	 * Injects the necessary CSS and JavaScript directly into the page footer.
	 */
	public function inline_page_assets(): void {
		?>
		<style type="text/css">
			/* -- Layout & Sizing -- */
			.wp-list-table .column-channel { width: 15%; }
			.wp-list-table .column-level { width: 10%; }
			.wp-list-table .column-message { width: auto; }
			.wp-list-table .column-timestamp { width: 15%; text-align: left; }
			.wpsl-time { color: #777; }

			/* -- Context Viewer -- */
			.wpsl-context-viewer { margin-top: 8px; border: 1px solid #ccd0d4; border-radius: 4px; background-color: #1e1e1e; color: #d4d4d4; font-family: monospace; }
			.wpsl-context-header { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background-color: #3c4043; border-bottom: 1px solid #5f6368; }
			.wpsl-context-header span { font-weight: bold; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
			.wpsl-copy-context { color: #8ab4f8 !important; text-decoration: none; cursor: pointer; }
			.wpsl-copy-context:hover { text-decoration: underline; }
			.wpsl-context-pre { white-space: pre-wrap; word-break: break-all; padding: 12px; margin: 0; max-height: 300px; overflow-y: auto; }

			/* -- Empty State -- */
			.wpsl-empty-state { padding: 20px; text-align: center; background-color: #fff; border: 1px solid #ccd0d4; margin-top: 20px; }
			.wpsl-empty-state h3 { margin: 0 0 10px; font-size: 1.2em; }
			.wpsl-empty-state p { margin: 0; color: #555; }
		</style>
		<script type="text/javascript">
			jQuery(function($) {
				'use strict';

				function handleToggleClick(e) {
					e.preventDefault();

					const $button = $(this);
					const $viewer = $button.next('.wpsl-context-viewer');

					if ($viewer.length) {
						const isExpanded = $button.attr('aria-expanded') === 'true';
						$button.attr('aria-expanded', String(!isExpanded));
						$viewer.toggle(!isExpanded);
					}
				}

				function handleCopyClick(e) {
					e.preventDefault();

					const $button = $(this);
					const $viewer = $button.closest('.wpsl-context-viewer');
					const $pre = $viewer.find('.wpsl-context-pre');

					if (!$pre.length) {
						alert('<?php echo esc_js( __( 'No context found to copy.', 'wp-simple-logger' ) ); ?>');
						return;
					}

					const textToCopy = $pre.text();
					const originalText = $button.text();
					const copiedText = '<?php echo esc_js( __( 'Copied!', 'wp-simple-logger' ) ); ?>';
					const copyFailedText = '<?php echo esc_js( __( 'Copy failed. Please try manually.', 'wp-simple-logger' ) ); ?>';
					const unsupportedText = '<?php echo esc_js( __( 'Copy not supported in this browser.', 'wp-simple-logger' ) ); ?>';

					if (navigator.clipboard && window.isSecureContext) {
						navigator.clipboard.writeText(textToCopy).then(() => {
							$button.text(copiedText);
							setTimeout(() => $button.text(originalText), 1500);
						}).catch(() => {
							alert(copyFailedText);
						});
					} else {
						const $temp = $('<textarea>')
							.val(textToCopy)
							.css({ position: 'absolute', left: '-9999px', top: '0' })
							.appendTo('body');

						$temp[0].select();
						try {
							const success = document.execCommand('copy');
							if (success) {
								$button.text(copiedText);
								setTimeout(() => $button.text(originalText), 1500);
							} else {
								alert(copyFailedText);
							}
						} catch (err) {
							alert(unsupportedText);
						}
						$temp.remove();
					}
				}

				function handleClearLogsClick(e) {
					const confirmText = $(this).data('confirm-text') || '<?php echo esc_js( __( 'Are you sure you want to clear the logs?', 'wp-simple-logger' ) ); ?>';
					if (!window.confirm(confirmText)) {
						e.preventDefault();
					}
				}

				$('.wpsl-context-toggle').on('click', handleToggleClick);
				$('.wpsl-copy-context').on('click', handleCopyClick);
				$('.wpsl-clear-logs-button').on('click', handleClearLogsClick);
			});
		</script>
		<?php
	}

	/**
	 * Renders the content of the log viewer page.
	 */
	public function render_page(): void {
		$this->get_list_table()->prepare_items();
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap wpsl-wrap">
			<h1 class="wp-heading-inline">
				<?php echo esc_html( (string) $this->config['page_title'] ); ?>
			</h1>
			<hr class="wp-header-end">
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>" />
				<?php
				$this->get_list_table()->search_box( esc_html__( 'Search Logs', 'wp-simple-logger' ), 'log_search' );

				if ( $this->get_list_table()->has_items() ) {
					$this->get_list_table()->display();
				} else {
					$this->display_empty_state_message();
				}
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Displays a message when no logs are found.
	 */
	private function display_empty_state_message(): void {
		// Use the sanitized filters instead of raw $_GET.
		$filters = $this->get_list_table()->get_filters_from_request();

		$is_filtered = (
			'' !== $filters['search'] ||
			'' !== $filters['channel'] ||
			'' !== $filters['level']
		);

		?>
		<div class="wpsl-empty-state">
			<?php if ( $is_filtered ) : ?>
				<h3><?php esc_html_e( 'No Logs Found', 'wp-simple-logger' ); ?></h3>
				<p><?php esc_html_e( 'No logs were found that match your current filter criteria.', 'wp-simple-logger' ); ?></p>
			<?php else : ?>
				<h3><?php esc_html_e( 'No Logs Recorded Yet', 'wp-simple-logger' ); ?></h3>
				<p><?php esc_html_e( 'Once your application starts logging events, they will appear here.', 'wp-simple-logger' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Processes all actions from the log viewer, like 'clear' or bulk 'delete'.
	 */
	public function process_actions(): void {
		$action = $this->get_list_table()->current_action();
		if ( false === $action ) {
			return;
		}

		if ( 'clear_logs' === $action ) {
			$this->handle_clear_logs_action();
			return;
		}

		if ( 'delete' !== $action ) {
			return;
		}

		$this->handle_bulk_delete_action();
	}

	/**
	 * Handles the 'clear logs' action.
	 */
	private function handle_clear_logs_action(): void {
		$nonce = isset( $_REQUEST['_wpnonce'] ) && is_string( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

		if ( false === wp_verify_nonce( $nonce, 'wpsl_clear_logs_nonce' ) ) {
			return;
		}

		if ( false === current_user_can( (string) $this->config['capability'] ) ) {
			wp_die( esc_html__( 'You do not have permission to clear logs.', 'wp-simple-logger' ) );
		}

		$channel = isset( $_GET['channel'] ) && is_string( $_GET['channel'] ) ? sanitize_key( $_GET['channel'] ) : '';

		$this->repository->clear( $channel );
		$this->set_admin_notice_and_redirect( esc_html__( 'Logs cleared successfully.', 'wp-simple-logger' ), true );
	}

	/**
	 * Handles the bulk 'delete' action.
	 */
	private function handle_bulk_delete_action(): void {
		$nonce = isset( $_REQUEST['_wpnonce'] ) && is_string( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

		if ( false === wp_verify_nonce( $nonce, 'bulk-logs' ) ) {
			return;
		}

		if ( false === current_user_can( (string) $this->config['capability'] ) ) {
			wp_die( esc_html__( 'You do not have permission to delete logs.', 'wp-simple-logger' ) );
		}

		$log_ids = isset( $_GET['log_ids'] ) && is_array( $_GET['log_ids'] )
			? array_values( array_map( 'intval', array_filter( $_GET['log_ids'], static fn ( $id ) => is_numeric( $id ) && (int) $id > 0 ) ) )
			: [];

		$deleted_count = 0;
		if ( 0 !== count( $log_ids ) ) {
			$deleted_count = $this->repository->delete_by_ids( $log_ids );
		}

		$message = sprintf(
			/* translators: %d is the number of logs deleted. */
			_n( '%d log deleted successfully.', '%d logs deleted successfully.', $deleted_count, 'wp-simple-logger' ),
			$deleted_count
		);

		$this->set_admin_notice_and_redirect( esc_html( $message ) );
	}

	/**
	 * Sets a transient with a message and redirects back to the log viewer page.
	 *
	 * @param string $message            The exact message string to display in the notice.
	 * @param bool   $remove_channel_key Whether to remove the 'channel' query arg from the URL.
	 */
	private function set_admin_notice_and_redirect( string $message, bool $remove_channel_key = false ): void {
		$transient_key = 'wpsl_admin_notice_' . get_current_user_id();
		set_transient( $transient_key, $message, 60 );

		$removed_keys = [ 'action', 'action2', '_wpnonce', 'log_ids', 's' ];
		if ( $remove_channel_key ) {
			$removed_keys[] = 'channel';
		}

		// Remove all action-related query args for a clean URL.
		$redirect_url = remove_query_arg( $removed_keys );

		wp_safe_redirect( $redirect_url );
		exit;
	}
}
