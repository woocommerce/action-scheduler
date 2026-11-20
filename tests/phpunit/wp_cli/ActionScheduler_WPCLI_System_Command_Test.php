<?php

use Action_Scheduler\WP_CLI\System_Command;

/**
 * @group wp-cli
 */
class ActionScheduler_WPCLI_System_Command_Test extends ActionScheduler_UnitTestCase {

	/**
	 * WP-CLI is not loaded by the test suite, so supply the parts the source command uses from the dev dependency.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		$wp_cli = dirname( __DIR__, 3 ) . '/vendor/wp-cli/wp-cli/php';

		require_once $wp_cli . '/class-wp-cli.php';
		require_once $wp_cli . '/utils.php';
		require_once $wp_cli . '/WP_CLI/Formatter.php';
	}

	/**
	 * The copy of Action Scheduler that is running should be the one marked active.
	 */
	public function test_source_all_marks_the_running_copy_active() {
		$active_file = ActionScheduler_SystemInformation::active_source_path() . 'action-scheduler.php';
		$this->assertArrayHasKey( $active_file, ActionScheduler_SystemInformation::get_sources() );

		ob_start();
		( new System_Command() )->source(
			array(),
			array(
				'all'      => true,
				'fullpath' => true,
				'format'   => 'json',
			)
		);
		$rows = json_decode( ob_get_clean(), true );

		$active = wp_list_filter( $rows, array( 'active' => 'yes' ) );
		$this->assertCount( 1, $active );
		$this->assertSame( $active_file, current( $active )['source'] );
	}
}
