<?php

/**
 * Tests for the Action Scheduler admin view.
 */
class ActionScheduler_AdminView_Test extends ActionScheduler_UnitTestCase {

	public function test_pastdue_actions_notice_replaces_placeholders() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		delete_transient( 'action_scheduler_last_pastdue_actions_check' );
		as_schedule_single_action( time() - 2 * DAY_IN_SECONDS, 'as_test_pastdue_notice_hook' );

		$reflection = new ReflectionClass( ActionScheduler_AdminView::class );
		$admin_view = $reflection->newInstanceWithoutConstructor();

		ob_start();
		$admin_view->maybe_check_pastdue_actions();
		$output = ob_get_clean();

		$this->assertStringContainsString( '1 <a href=', $output, 'The number of past-due actions is shown.' );
		$this->assertStringContainsString( 'status=past-due', $output, 'The link points to the past-due actions list.' );
		$this->assertStringNotContainsString( '%1$d', $output );
		$this->assertStringNotContainsString( '%2$s', $output );
	}
}
