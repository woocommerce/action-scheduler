<?php

/**
 * Class ActionScheduler_PastDueMonitor
 *
 * @codeCoverageIgnore
 */
class ActionScheduler_PastDueMonitor {

	private static $monitor = null;

	protected $threshold_seconds;
	protected $threshold_minimum;
	protected $threshold_email_minimum;
	protected $interval_check;
	protected $interval_email_seconds;
	protected $num_pastdue_actions = 0;
	protected $query_args = array();

	/**
	 * @return ActionScheduler_PastDueMonitor
	 *
	 * @codeCoverageIgnore
	 */
	public static function instance() {

		if ( empty( self::$monitor ) ) {
			$class = apply_filters( 'action_scheduler_pastdue_actions_monitor_class', 'ActionScheduler_PastDueMonitor' );
			self::$monitor = new $class();
		}

		return self::$monitor;
	}

	public function init() {
		$this->threshold_seconds       = absint( apply_filters( 'action_scheduler_pastdue_actions_seconds', DAY_IN_SECONDS ) );
		$this->threshold_minimum       = absint( apply_filters( 'action_scheduler_pastdue_actions_min', 1 ) );
		$this->interval_check          = absint( apply_filters( 'action_scheduler_pastdue_actions_check_interval', ( $this->threshold_seconds / 4 ) ) );
		$this->interval_email_seconds  = absint( apply_filters( 'action_scheduler_pastdue_actions_email_interval', HOUR_IN_SECONDS ) );
		$this->threshold_email_minimum = absint( apply_filters( 'action_scheduler_pastdue_actions_email_min', $this->threshold_minimum ) );

		$this->maybe_send_email();

		if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || false == DOING_AJAX ) ) {
			add_action( 'admin_notices', array( $this, 'action__admin_notices' ) );
		}
	}

	protected function maybe_send_email() {
		if ( ! $this->critical() ) {
			return;
		}

		$transient = get_transient( 'action_scheduler_pastdue_actions_last_email' );

		if ( ! empty( $transient ) ) {
			return;
		}

		if ( $this->num_pastdue_actions < $this->threshold_email_min ) {
			return;
		}

		$to      = get_bloginfo( 'admin_email' );
		$from    = '';
		$subject = '';
		$message = '';

		set_transient( 'action_scheduler_pastdue_actions_last_email', time(), $this->interval_email_seconds );

		wp_mail( $to, $subject, $message, "From: $from" );
	}

	protected function critical() {
		// Allow third-parties to preempt the default check logic.
		$check = apply_filters( 'action_scheduler_pastdue_actions_check_pre', null );

		// If no third-party preempted and there are no past-due actions, return early.
		if ( ! is_null( $check ) ) {
			return $check;
		}

		if ( ! empty( $this->num_pastdue_actions ) ) {
			return true;
		}

		$transient = get_transient( 'action_scheduler_pastdue_actions_pause' );

		if ( ! empty( $transient ) ) {
			return false;
		}

		# Scheduled actions query arguments.
		$this->query_args = array(
			'date'     => as_get_datetime_object( time() - $this->threshold_seconds ),
			'status'   => ActionScheduler_Store::STATUS_PENDING,
			'per_page' => $this->threshold_minimum,
		);

		$store = ActionScheduler_Store::instance();
		$this->num_pastdue_actions = ( int ) $store->query_actions( $this->query_args, 'count' );

		# Check if past-due actions count is greater than or equal to threshold.
		$check = ( $this->num_pastdue_actions >= $this->threshold_minimum );
		$check = ( bool ) apply_filters( 'action_scheduler_pastdue_actions_check', $check, $this->num_pastdue_actions, $this->threshold_seconds, $this->threshold_minimum );

		if ( ! $check ) {
			set_transient( 'action_scheduler_pastdue_actions_pause', time(), $this->interval_check );
		}

		return $check;
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

		# Filter to prevent showing notice (ex: inappropriate user).
		if ( ! apply_filters( 'action_scheduler_check_pastdue_actions', current_user_can( 'manage_options' ) ) ) {
			return;
		}

		if ( ! $this->critical() ) {
			return;
		}

		$actions_url = add_query_arg( array(
			'page'   => 'action-scheduler',
			'status' => 'past-due',
			'order'  => 'asc',
		), admin_url( 'tools.php' ) );

		# Print notice.
		echo '<div class="notice notice-warning"><p>';
		printf(
			// translators: 1) is the number of affected actions, 2) is a link to an admin screen.
			_n(
				'<strong>Action Scheduler:</strong> %1$d <a href="%2$s">past-due action</a> found; something may be wrong. <a href="https://actionscheduler.org/faq/#my-site-has-past-due-actions-what-can-i-do" target="_blank">Read documentation &raquo;</a>',
				'<strong>Action Scheduler:</strong> %1$d <a href="%2$s">past-due actions</a> found; something may be wrong. <a href="https://actionscheduler.org/faq/#my-site-has-past-due-actions-what-can-i-do" target="_blank">Read documentation &raquo;</a>',
				$this->num_pastdue_actions,
				'action-scheduler'
			),
			$this->num_pastdue_actions,
			esc_attr( esc_url( $actions_url ) )
		);
		echo '</p></div>';

		# Facilitate third-parties to evaluate and print notices.
		do_action( 'action_scheduler_pastdue_actions_extra_notices', $this->query_args );
	}

}
