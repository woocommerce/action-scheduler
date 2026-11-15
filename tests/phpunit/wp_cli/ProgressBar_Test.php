<?php

use Action_Scheduler\WP_CLI\ProgressBar;

/**
 * @group wp-cli
 */
class ProgressBar_Test extends ActionScheduler_UnitTestCase {

	/**
	 * WP-CLI is not loaded by the test suite, so supply the no-op progress bar it uses when output is not sent to
	 * a terminal from the dev dependency.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		if ( ! class_exists( '\WP_CLI\NoOp', false ) ) {
			require_once dirname( __DIR__, 3 ) . '/vendor/wp-cli/wp-cli/php/WP_CLI/NoOp.php';
		}
	}

	public function test_current_counts_ticks_when_wp_cli_supplies_a_no_op_progress_bar() {
		$progress_bar = $this->get_progress_bar( 3 );
		$this->assertSame( 0, $progress_bar->current(), 'No ticks have been performed yet.' );

		$progress_bar->tick();
		$progress_bar->tick();
		$this->assertSame( 2, $progress_bar->current() );

		$progress_bar->tick();
		$this->assertSame( 3, $progress_bar->current() );
	}

	public function test_current_is_reset_when_progress_bar_is_finished() {
		$progress_bar = $this->get_progress_bar( 3 );

		$progress_bar->tick();
		$progress_bar->tick();
		$progress_bar->finish();
		$this->assertSame( 0, $progress_bar->current(), 'Finishing the progress bar resets the count.' );

		$progress_bar->tick();
		$this->assertSame( 1, $progress_bar->current(), 'Ticks are counted from zero again once a new progress bar is set up.' );
	}

	/**
	 * The ProgressBar constructor insists on WP-CLI, so it is bypassed, and WP-CLI's no-op progress bar (which it
	 * supplies when output is piped, for example when running from the system scheduler) is used.
	 *
	 * @param int $count Total number of ticks to be performed.
	 *
	 * @return ProgressBar
	 */
	private function get_progress_bar( $count ) {
		return new class( $count ) extends ProgressBar {
			/**
			 * @param int $count Total number of ticks to be performed.
			 */
			public function __construct( $count ) {
				$this->total_ticks = 0;
				$this->message     = '';
				$this->count       = $count;
				$this->interval    = 100;
			}

			/**
			 * Set up WP-CLI's no-op progress bar.
			 */
			protected function setup_progress_bar() {
				$this->progress_bar = new \WP_CLI\NoOp();
			}
		};
	}
}
