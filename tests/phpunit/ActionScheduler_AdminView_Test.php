<?php

/**
 * Tests for the Action Scheduler admin view.
 */
class ActionScheduler_AdminView_Test extends ActionScheduler_UnitTestCase {

	public function tearDown(): void {
		unset( $_GET['tab'] );
		parent::tearDown();
	}

	public function test_processes_wc_scheduled_actions_tab() {
		$_GET['tab'] = 'action-scheduler';
		$admin_view  = $this->getMockBuilder( ActionScheduler_AdminView::class )
			->onlyMethods( array( 'process_admin_ui' ) )
			->getMock();

		$admin_view->expects( $this->once() )->method( 'process_admin_ui' );
		$admin_view->maybe_process_wc_admin_ui();
	}

	public function test_ignores_other_wc_status_tabs() {
		$_GET['tab'] = 'logs';
		$admin_view  = $this->getMockBuilder( ActionScheduler_AdminView::class )
			->onlyMethods( array( 'process_admin_ui' ) )
			->getMock();

		$admin_view->expects( $this->never() )->method( 'process_admin_ui' );
		$admin_view->maybe_process_wc_admin_ui();
	}
}
