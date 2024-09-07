<?php

/**
 * Class ActionScheduler_PastDueMonitor
 *
 * @codeCoverageIgnore
 */
class ActionScheduler_PastDueMonitor {

	const TRANSIENT_LAST_EMAIL     = 'action_scheduler_pastdue_actions_last_email';
	const TRANSIENT_CHECK_INTERVAL = 'action_scheduler_last_pastdue_actions_check';

	/**
	 * Instance.
	 *
	 * @var null|self
	 */
	private static $monitor = null;

	/**
	 * Number of seconds in the past to qualify as past-due action.
	 *
	 * @var int
	 */
	protected $threshold_seconds;

	/**
	 * Number of minimum past-due actions to display admin notice.
	 *
	 * @var int
	 */
	protected $threshold_minimum;

	/**
	 * Number of minimum past-due actions to send email notice.
	 *
	 * @var int
	 */
	protected $threshold_email_minimum;

	/**
	 * Number of seconds before past-due actions check after
	 * negative (not flooded) check.
	 *
	 * @var int
	 */
	protected $interval_check;

	/**
	 * Number of seconds between email notices.
	 *
	 * @var int
	 */
	protected $interval_email_seconds;

	/**
	 * Number of past-due actions (determined by thresholds).
	 *
	 * @var int
	 */
	protected $num_pastdue_actions = 0;

	/**
	 * Query arguments for past-due actions.
	 *
	 * @var array<string, string|int>
	 */
	protected $query_args = array();

	/**
	 * Notification methods.
	 *
	 * @var array<string, bool>
	 */
	protected $notify_methods = array();

	/**
	 * Email address to send notification.
	 *
	 * @var string
	 */
	protected $notify_email_to;

	/**
	 * Get singleton instance.
	 *
	 * @return ActionScheduler_PastDueMonitor
	 *
	 * @codeCoverageIgnore
	 */
	public static function instance() {

		if ( empty( self::$monitor ) ) {
			$class         = apply_filters( 'action_scheduler_pastdue_actions_monitor_class', 'ActionScheduler_PastDueMonitor' );
			self::$monitor = new $class();
		}

		return self::$monitor;
	}

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public function init() {
		$this->threshold_seconds       = absint( apply_filters( 'action_scheduler_pastdue_actions_seconds', DAY_IN_SECONDS ) );
		$this->threshold_minimum       = absint( apply_filters( 'action_scheduler_pastdue_actions_min', 1 ) );
		$this->interval_check          = absint( apply_filters( 'action_scheduler_pastdue_actions_check_interval', ( $this->threshold_seconds / 4 ) ) );
		$this->interval_email_seconds  = absint( apply_filters( 'action_scheduler_pastdue_actions_email_interval', HOUR_IN_SECONDS ) );
		$this->threshold_email_minimum = absint( apply_filters( 'action_scheduler_pastdue_actions_email_min', $this->threshold_minimum ) );
		$this->notify_methods          = $this->notify_methods();

		$notify_email_to = apply_filters( 'action_scheduler_pastdue_monitor_notify_email_to', get_site_option( 'admin_email' ) );

		if ( ! is_string( $notify_email_to ) || ! is_email( $notify_email_to ) ) {
			trigger_error( 'Invalid email address provided for past-due actions flooded email notification: using site\'s administrator email address.', E_USER_WARNING );
			$notify_email_to = get_site_option( 'admin_email' );
		}

		$this->notify_email_to = $notify_email_to;

		add_action( 'action_scheduler_stored_action', array( $this, 'on_action_stored' ) );

		if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || empty( DOING_AJAX ) ) ) {
			add_action( 'admin_notices', array( $this, 'action__admin_notices' ) );
		}
	}

	/**
	 * Check if notification method enabled.
	 *
	 * @param string $method
	 * @return bool
	 */
	protected function notify( $method ) {
		return ! empty( $this->notify_methods[ $method ] );
	}

	/**
	 * Get enabled notification methods.
	 *
	 * @return array<string, bool>
	 */
	protected function notify_methods() {
		$all = array(
			'admin' => true,
			'email' => true,
		);

		$default          = $all;
		$default['admin'] = function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
		$methods          = $default;

		// Apply deprecated filter.
		if ( has_filter( 'action_scheduler_check_pastdue_actions' ) ) {
			$methods['admin'] = apply_filters_deprecated(
				'action_scheduler_check_pastdue_actions',
				array( $methods['admin'] ),
				'', // todo: add version number
				'action_scheduler_pastdue_monitor_notify'
			);
		}

		// Allow site devs to specify notification preference.
		$methods = apply_filters( 'action_scheduler_pastdue_monitor_notify', $methods );

		// Support for scalar and bool values (ex: `__return_false`).
		if ( is_scalar( $methods ) || is_bool( $methods ) ) {
			return boolval( $methods ) ? $all : array();
		}

		// Insist on an array.
		if ( ! is_array( $methods ) ) {
			return $default;
		}

		// Clear out unsupported methods.
		$methods = array_intersect_key( $methods, $all );

		return $methods;
	}

	/**
	 * Check if threshold for past-due actions is flooded.
	 *
	 * @return bool
	 */
	protected function flooded() {
		$transient = get_transient( self::TRANSIENT_CHECK_INTERVAL );

		if ( ! empty( $transient ) ) {
			return false;
		}

		// Scheduled actions query arguments.
		$this->query_args = array(
			'date'     => as_get_datetime_object( time() - $this->threshold_seconds ),
			'status'   => ActionScheduler_Store::STATUS_PENDING,
			'per_page' => $this->threshold_minimum,
		);

		$store = ActionScheduler_Store::instance();

		$this->num_pastdue_actions = absint( $store->query_actions( $this->query_args, 'count' ) );

		// Check if past-due actions count is greater than or equal to threshold.
		$flooded = ( $this->num_pastdue_actions >= $this->threshold_minimum );
		$flooded = (bool) apply_filters( 'action_scheduler_pastdue_actions_check', $flooded, $this->num_pastdue_actions, $this->threshold_seconds, $this->threshold_minimum );

		set_transient( self::TRANSIENT_CHECK_INTERVAL, time(), $this->interval_check );

		return $flooded;
	}

	/**
	 * Action: action_scheduler_stored_action
	 *
	 * Delete check interval transient, perform flooded check,
	 * and maybe send email.
	 *
	 * @return void
	 */
	public function on_action_stored() {
		if ( 'action_scheduler_stored_action' !== current_action() ) {
			return;
		}

		// Allow third-parties to preempt the default check logic.
		$pre = apply_filters( 'action_scheduler_pastdue_actions_check_pre', null );

		// If no third-party preempted and there are no past-due actions, return early.
		if ( ! is_null( $pre ) ) {
			return;
		}

		delete_transient( self::TRANSIENT_CHECK_INTERVAL );

		// Removing the callback before querying actions is necessary to prevent loop.
		remove_action( 'action_scheduler_stored_action', array( $this, 'on_action_stored' ) );

		if ( ! $this->flooded() ) {
			return;
		}

		$this->maybe_send_email();
	}

	/**
	 * Maybe send email notice of past-due actions over threshold.
	 *
	 * @return void
	 */
	protected function maybe_send_email() {
		if ( ! $this->notify( 'email' ) ) {
			return;
		}

		$transient = get_transient( self::TRANSIENT_LAST_EMAIL );

		if ( ! empty( $transient ) ) {
			return;
		}

		if ( $this->num_pastdue_actions < $this->threshold_email_minimum ) {
			return;
		}

		$sitename = wp_parse_url( network_home_url(), PHP_URL_HOST );

		if ( null !== $sitename ) {
			if ( str_starts_with( $sitename, 'www.' ) ) {
				$sitename = substr( $sitename, 4 );
			}
		}

		$to      = $this->notify_email_to;
		$subject = sprintf( 'Action Scheduler: past-due scheduled actions (%s)', $sitename );
		$message = $this->message();
		$headers = array(
			'Content-type: text/html; charset=UTF-8',
		);

		set_transient( self::TRANSIENT_LAST_EMAIL, time(), $this->interval_email_seconds );

		wp_mail( $to, $subject, $message, $headers );

		do_action( 'action_scheduler_pastdue_monitor_notified_email' );
	}

	/**
	 * Action: admin_notices
	 *
	 * Maybe check past-due actions, and print notice.
	 */
	public function action__admin_notices() {
		if ( 'admin_notices' !== current_action() ) {
			return;
		}

		if ( ! $this->notify( 'admin' ) ) {
			return;
		}

		// Allow third-parties to preempt the default check logic.
		$pre = apply_filters( 'action_scheduler_pastdue_actions_check_pre', null );

		// If no third-party preempted and there are no past-due actions, return early.
		if ( ! is_null( $pre ) ) {
			return;
		}

		if ( ! $this->flooded() ) {
			return;
		}

		// Print notice.
		echo '<div class="notice notice-warning"><p>';
		echo wp_kses(
			$this->message(),
			array(
				'strong' => array(),
				'a'      => array(
					'target' => true,
					'href'   => true,
				),
			)
		);
		echo '</p></div>';

		// Facilitate third-parties to evaluate and print notices.
		do_action( 'action_scheduler_pastdue_actions_extra_notices', $this->query_args );
	}

	/**
	 * Message for admin notice and email notice.
	 *
	 * @return string
	 */
	protected function message() {
		$actions_url = add_query_arg(
			array(
				'page'   => 'action-scheduler',
				'status' => 'past-due',
				'order'  => 'asc',
			),
			admin_url( 'tools.php' )
		);

		return sprintf(
			// translators: 1) is the number of affected actions, 2) is a link to an admin screen.
			_n(
				'<strong>Action Scheduler:</strong> %1$d <a href="%2$s">past-due action</a> found; something may be wrong. <a href="https://actionscheduler.org/faq/#my-site-has-past-due-actions-what-can-i-do" target="_blank">Read documentation &raquo;</a>',
				'<strong>Action Scheduler:</strong> %1$d <a href="%2$s">past-due actions</a> found; something may be wrong. <a href="https://actionscheduler.org/faq/#my-site-has-past-due-actions-what-can-i-do" target="_blank">Read documentation &raquo;</a>',
				$this->num_pastdue_actions,
				'action-scheduler'
			),
			$this->num_pastdue_actions,
			esc_url( $actions_url )
		);
	}

}
