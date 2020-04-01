<?php

/**
 * Class ActionScheduler_AdminView
 * @codeCoverageIgnore
 */
class ActionScheduler_AdminView extends ActionScheduler_AdminView_Deprecated {

	private static $admin_view = NULL;

	private static $screen_id = 'tools_page_action-scheduler';

	/** @var ActionScheduler_ListTable */
	protected $list_table;

	/**
	 * @return ActionScheduler_AdminView
	 * @codeCoverageIgnore
	 */
	public static function instance() {

		if ( empty( self::$admin_view ) ) {
			$class = apply_filters('action_scheduler_admin_view_class', 'ActionScheduler_AdminView');
			self::$admin_view = new $class();
		}

		return self::$admin_view;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function init() {
		if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || false == DOING_AJAX ) ) {

			if ( class_exists( 'WooCommerce' ) ) {
				add_action( 'woocommerce_admin_status_content_action-scheduler', array( $this, 'render_admin_ui' ) );
				add_action( 'woocommerce_system_status_report', array( $this, 'system_status_report' ) );
				add_filter( 'woocommerce_admin_status_tabs', array( $this, 'register_system_status_tab' ) );
			}

			add_action( 'admin_menu', array( $this, 'register_menu' ) );
			add_action( 'admin_notices', array( $this, 'maybe_check_past_actions' ) );
			add_action( 'current_screen', array( $this, 'add_help_tabs' ) );
		}
	}

	public function system_status_report() {
		$table = new ActionScheduler_wcSystemStatus( ActionScheduler::store() );
		$table->render();
	}

	/**
	 * Registers action-scheduler into WooCommerce > System status.
	 *
	 * @param array $tabs An associative array of tab key => label.
	 * @return array $tabs An associative array of tab key => label, including Action Scheduler's tabs
	 */
	public function register_system_status_tab( array $tabs ) {
		$tabs['action-scheduler'] = __( 'Scheduled Actions', 'action-scheduler' );

		return $tabs;
	}

	/**
	 * Include Action Scheduler's administration under the Tools menu.
	 *
	 * A menu under the Tools menu is important for backward compatibility (as that's
	 * where it started), and also provides more convenient access than the WooCommerce
	 * System Status page, and for sites where WooCommerce isn't active.
	 */
	public function register_menu() {
		$hook_suffix = add_submenu_page(
			'tools.php',
			__( 'Scheduled Actions', 'action-scheduler' ),
			__( 'Scheduled Actions', 'action-scheduler' ),
			'manage_options',
			'action-scheduler',
			array( $this, 'render_admin_ui' )
		);
		add_action( 'load-' . $hook_suffix , array( $this, 'process_admin_ui' ) );
	}

	/**
	 * Triggers processing of any pending actions.
	 */
	public function process_admin_ui() {
		$this->get_list_table();
	}

	/**
	 * Renders the Admin UI
	 */
	public function render_admin_ui() {
		$table = $this->get_list_table();
		$table->display_page();
	}

	/**
	 * Get the admin UI object and process any requested actions.
	 *
	 * @return ActionScheduler_ListTable
	 */
	protected function get_list_table() {
		if ( null === $this->list_table ) {
			$this->list_table = new ActionScheduler_ListTable( ActionScheduler::store(), ActionScheduler::logger(), ActionScheduler::runner() );
			$this->list_table->process_actions();
		}

		return $this->list_table;
	}

	/**
	 * Maybe check past actions, and display alert.
	 *
	 * @todo break into pieces
	 * @todo test
	 * @todo add alert message
	 * @todo confirm and set seconds past
	 * @todo confirm and set min past actions count
	 * @todo add error_log() call
	 */
	public function maybe_check_past_actions() {

		# Filter to prevent checking actions (ex: inappropriate user).
		if ( apply_filters( 'action_scheduler_check_past_actions', false ) )
			return;

		# Use transient to prevent checking on every admin page load.
		$transient_key = 'action_scheduler_last_past_actions_check';
		$last_check = get_transient( $transient_key );

		# If transient exists, we're inside of the interval, so abort.
		if ( !empty( $last_check ) )
			return;

		# Set thresholds.
		$past_seconds = ( int ) apply_filters( 'action_scheduler_past_actions_seconds', DAY_IN_SECONDS );
		$min_past_actions = ( int ) apply_filters( 'action_scheduler_alert_past_actions_count', 20 );

		# Allow third-parties to preempt the default check logic.
		$check = apply_filters( 'action_scheduler_past_action_check_pre', null );

		$query_args = array(
			'status'   => ActionScheduler_Store::STATUS_PENDING,
			'per_page' => $min_past_actions,
			'date'     => time() - $past_seconds,
		);

		# If no third-party preempted, run default check.
		if ( is_null( $check ) ) {
			$num_past_actions = $store->query_actions( $query_args, 'count' );
			$check = apply_filters( 'action_scheduler_past_actions_check', ( $num_past_actions < $min_past_actions ), $num_past_actions, $past_seconds, $min_past_actions );

			# Set transient to only check on designated interval.
			$interval = ( int ) apply_filters( 'action_scheduler_check_past_actions_interval', round( $past_seconds / 4 ), $past_seconds );
			set_transient( $transient_key, 1, $interval );
		}

		$check = ( bool ) $check;

		# If check failed, do not display notice.
		if ( !$check )
			return;

		# Print notice.
		printf( __( '<div class="%s"><p>%s</p></div>', 'notice notice-warning', '{{ ALERT MESSAGE }}', 'action-scheduler' ) );

		# Facilitate third-parties to evaluate and print notices.
		do_action( 'action_scheduler_past_actions_notices', $query_args );
	}

	/**
	 * Provide more information about the screen and its data in the help tab.
	 */
	public function add_help_tabs() {
		$screen = get_current_screen();

		if ( ! $screen || self::$screen_id != $screen->id ) {
			return;
		}

		$as_version = ActionScheduler_Versions::instance()->latest_version();
		$screen->add_help_tab(
			array(
				'id'      => 'action_scheduler_about',
				'title'   => __( 'About', 'action-scheduler' ),
				'content' =>
					'<h2>' . sprintf( __( 'About Action Scheduler %s', 'action-scheduler' ), $as_version ) . '</h2>' .
					'<p>' .
						__( 'Action Scheduler is a scalable, traceable job queue for background processing large sets of actions. Action Scheduler works by triggering an action hook to run at some time in the future. Scheduled actions can also be scheduled to run on a recurring schedule.', 'action-scheduler' ) .
					'</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'action_scheduler_columns',
				'title'   => __( 'Columns', 'action-scheduler' ),
				'content' =>
					'<h2>' . __( 'Scheduled Action Columns', 'action-scheduler' ) . '</h2>' .
					'<ul>' .
					sprintf( '<li><strong>%1$s</strong>: %2$s</li>', __( 'Hook', 'action-scheduler' ), __( 'Name of the action hook that will be triggered.', 'action-scheduler' ) ) .
					sprintf( '<li><strong>%1$s</strong>: %2$s</li>', __( 'Status', 'action-scheduler' ), __( 'Action statuses are Pending, Complete, Canceled, Failed', 'action-scheduler' ) ) .
					sprintf( '<li><strong>%1$s</strong>: %2$s</li>', __( 'Arguments', 'action-scheduler' ), __( 'Optional data array passed to the action hook.', 'action-scheduler' ) ) .
					sprintf( '<li><strong>%1$s</strong>: %2$s</li>', __( 'Group', 'action-scheduler' ), __( 'Optional action group.', 'action-scheduler' ) ) .
					sprintf( '<li><strong>%1$s</strong>: %2$s</li>', __( 'Recurrence', 'action-scheduler' ), __( 'The action\'s schedule frequency.', 'action-scheduler' ) ) .
					sprintf( '<li><strong>%1$s</strong>: %2$s</li>', __( 'Scheduled', 'action-scheduler' ), __( 'The date/time the action is/was scheduled to run.', 'action-scheduler' ) ) .
					sprintf( '<li><strong>%1$s</strong>: %2$s</li>', __( 'Log', 'action-scheduler' ), __( 'Activity log for the action.', 'action-scheduler' ) ) .
					'</ul>',
			)
		);
	}
}
