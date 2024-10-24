<?php

namespace Action_Scheduler\WP_CLI;

/**
 * WP_CLI progress bar for Action Scheduler.
 */

/**
 * Class ProgressBar
 *
 * @package Action_Scheduler\WP_CLI
 *
 * @since 3.0.0
 *
 * @codeCoverageIgnore
 */
class ProgressBar {

	/**
	 * Current number of ticks.
	 *
	 * @var integer
	 */
	protected $total_ticks;

	/**
	 * Total number of ticks.
	 *
	 * @var integer
	 */
	protected $count;

	/**
	 * Progress bar update interval.
	 *
	 * @var integer
	 */
	protected $interval;

	/**
	 * Progress bar message.
	 *
	 * @var string
	 */
	protected $message;

	/**
	 * Instance.
	 *
	 * @var \cli\progress\Bar
	 */
	protected $progress_bar;

	/**
	 * ProgressBar constructor.
	 *
	 * @param string  $message    Text to display before the progress bar.
	 * @param integer $count      Total number of ticks to be performed.
	 * @param integer $interval   Optional. The interval in milliseconds between updates. Default 100.
	 *
	 * @throws \Exception When this is not run within WP CLI.
	 */
	public function __construct( $message, $count, $interval = 100 ) {
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			/* translators: %s php class name */
			throw new \Exception( sprintf( __( 'The %s class can only be run within WP CLI.', 'action-scheduler' ), __CLASS__ ) );
		}

		$this->total_ticks = 0;
		$this->message     = $message;
		$this->count       = $count;
		$this->interval    = $interval;
	}

	/**
	 * Increment the progress bar ticks.
	 */
	public function tick() {
		if ( null === $this->progress_bar ) {
			$this->setup_progress_bar();
		}

		$this->progress_bar->tick();
		$this->total_ticks++;

		// TODO: add version.
		do_action_deprecated( 'action_scheduler/progress_tick', $this->total_ticks, '', 'action_scheduler_progress_tick' ); // phpcs:ignore WordPress.NamingConvetions.ValidHookName.UseUnderscores
		do_action( 'action_scheduler_progress_tick', $this->total_ticks );
	}

	/**
	 * Get the progress bar tick count.
	 *
	 * @return int
	 */
	public function current() {
		return $this->progress_bar ? $this->progress_bar->current() : 0;
	}

	/**
	 * Finish the current progress bar.
	 */
	public function finish() {
		if ( null !== $this->progress_bar ) {
			$this->progress_bar->finish();
		}

		$this->progress_bar = null;
	}

	/**
	 * Set the message used when creating the progress bar.
	 *
	 * @param string $message The message to be used when the next progress bar is created.
	 */
	public function set_message( $message ) {
		$this->message = $message;
	}

	/**
	 * Set the count for a new progress bar.
	 *
	 * @param integer $count The total number of ticks expected to complete.
	 */
	public function set_count( $count ) {
		$this->count = $count;
		$this->finish();
	}

	/**
	 * Set up the progress bar.
	 */
	protected function setup_progress_bar() {
		$this->progress_bar = \WP_CLI\Utils\make_progress_bar(
			$this->message,
			$this->count,
			$this->interval
		);
	}
}
