<?php

/**
 * Tests for how the queue runner handles an action whose stored schedule references an unrecognized
 * class: it is marked failed (for operator review) rather than cancelled, and a forced run executes
 * its callback regardless of the unreadable schedule.
 *
 * @see https://github.com/woocommerce/action-scheduler/issues/1318
 *
 * @group tables
 */
class ActionScheduler_UnrecognizedSchedule_Test extends ActionScheduler_UnitTestCase {

	public function setUp(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->actionscheduler_actions}" );
		parent::setUp();
	}

	/**
	 * Persist a real, due, pending action, then overwrite its stored schedule with a blob that is a
	 * valid outer schedule nesting an unrecognized class — which the deserializer resolves to an
	 * ActionScheduler_UnrecognizedSchedule.
	 *
	 * @param string $hook Hook to schedule.
	 * @return int Action ID.
	 */
	private function store_due_action_with_unrecognized_schedule( $hook ) {
		global $wpdb;

		$store     = new ActionScheduler_DBStore();
		$action    = new ActionScheduler_Action( $hook, array(), new ActionScheduler_SimpleSchedule( as_get_datetime_object( '1 hour ago' ) ) );
		$action_id = $store->save_action( $action );

		// A valid third party schedule (a placeholder in the safe parse, so nothing is instantiated)
		// whose property holds a class Action Scheduler does not recognize.
		$nul       = chr( 0 );
		$prop      = $nul . '*' . $nul . 'timestamp';
		$container = 'ActionScheduler_Test_Custom_Schedule';
		$unknown   = 'O:14:"Some_Unknown_X":0:{}';
		$blob      = 'O:' . strlen( $container ) . ':"' . $container . '":1:{s:' . strlen( $prop ) . ':"' . $prop . '";' . $unknown . '}';

		$wpdb->update(
			$wpdb->actionscheduler_actions,
			array( 'schedule' => $blob ),
			array( 'action_id' => $action_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $action_id;
	}

	/**
	 * The deserializer resolves the tampered schedule to the placeholder, so the action fetches as a
	 * normal (displayable) action rather than a null action.
	 */
	public function test_action_fetches_with_unrecognized_schedule_placeholder() {
		$store     = new ActionScheduler_DBStore();
		$action_id = $this->store_due_action_with_unrecognized_schedule( 'as_test_unrecognized_fetch' );

		$fetched = $store->fetch_action( $action_id );

		$this->assertNotInstanceOf( 'ActionScheduler_NullAction', $fetched );
		$this->assertInstanceOf( 'ActionScheduler_UnrecognizedSchedule', $fetched->get_schedule() );
	}

	/**
	 * Automatic processing must mark an unrecognized-schedule action failed (for review) rather than
	 * running it or cancelling it.
	 */
	public function test_unrecognized_schedule_action_is_failed_not_run_or_cancelled() {
		$hook = 'as_test_unrecognized_auto';
		$ran  = 0;
		add_action(
			$hook,
			function () use ( &$ran ) {
				++$ran;
			}
		);

		$notified = false;
		add_action(
			'action_scheduler_unrecognized_schedule_action',
			function () use ( &$notified ) {
				$notified = true;
			}
		);

		$store     = new ActionScheduler_DBStore();
		$runner    = ActionScheduler_Mocker::get_queue_runner( $store );
		$action_id = $this->store_due_action_with_unrecognized_schedule( $hook );

		$runner->process_action( $action_id );

		$this->assertSame( ActionScheduler_Store::STATUS_FAILED, $store->get_status( $action_id ), 'The action should be failed, not cancelled or run.' );
		$this->assertSame( 0, $ran, 'An unrecognized-schedule action must not run automatically.' );
		$this->assertTrue( $notified, 'The action_scheduler_unrecognized_schedule_action hook should fire.' );

		remove_all_actions( $hook );
		remove_all_actions( 'action_scheduler_unrecognized_schedule_action' );
	}

	/**
	 * A forced run (as a site operator would trigger from the admin list table) executes the action's
	 * callback despite the unreadable schedule, flushing the work, and completes the action.
	 */
	public function test_forced_run_executes_unrecognized_schedule_action() {
		$hook = 'as_test_unrecognized_forced';
		$ran  = 0;
		add_action(
			$hook,
			function () use ( &$ran ) {
				++$ran;
			}
		);

		$store     = new ActionScheduler_DBStore();
		$runner    = ActionScheduler_Mocker::get_queue_runner( $store );
		$action_id = $this->store_due_action_with_unrecognized_schedule( $hook );

		// First, automatic processing marks it failed.
		$runner->process_action( $action_id );
		$this->assertSame( ActionScheduler_Store::STATUS_FAILED, $store->get_status( $action_id ) );

		// Then a forced run executes the callback and completes it.
		$runner->force_run_action( $action_id, 'Test' );

		$this->assertSame( 1, $ran, 'A forced run must execute the action callback.' );
		$this->assertSame( ActionScheduler_Store::STATUS_COMPLETE, $store->get_status( $action_id ) );

		remove_all_actions( $hook );
	}

	/**
	 * The admin list table renders an unrecognized schedule with a clear label rather than falling
	 * through to the NullSchedule "async" display.
	 */
	public function test_list_table_displays_unrecognized_schedule() {
		$list_table = $this->make_list_table();
		$method     = new ReflectionMethod( $list_table, 'get_schedule_display_string' );
		$method->setAccessible( true );

		$this->assertSame( 'Unrecognized schedule', $method->invoke( $list_table, new ActionScheduler_UnrecognizedSchedule() ) );
	}

	/**
	 * The list table "Run" row action force-runs a failed action (previously impossible), so an
	 * operator can flush stuck work.
	 */
	public function test_list_table_run_force_runs_a_failed_action() {
		$hook = 'as_test_unrecognized_listtable';
		$ran  = 0;
		add_action(
			$hook,
			function () use ( &$ran ) {
				++$ran;
			}
		);

		$store     = new ActionScheduler_DBStore();
		$runner    = ActionScheduler_Mocker::get_queue_runner( $store );
		$action_id = $this->store_due_action_with_unrecognized_schedule( $hook );

		$runner->process_action( $action_id ); // Automatic pass marks it failed.
		$this->assertSame( ActionScheduler_Store::STATUS_FAILED, $store->get_status( $action_id ) );

		$list_table = $this->make_list_table( $store, $runner );
		$method     = new ReflectionMethod( $list_table, 'process_row_action' );
		$method->setAccessible( true );
		$method->invoke( $list_table, $action_id, 'run' );

		$this->assertSame( 1, $ran, 'The list table Run action should force-run a failed action.' );
		$this->assertSame( ActionScheduler_Store::STATUS_COMPLETE, $store->get_status( $action_id ) );

		remove_all_actions( $hook );
	}

	/**
	 * The notice flag is set when an unrecognized-schedule failure is noted, and cleared by a valid
	 * dismissal request.
	 */
	public function test_notice_flag_is_set_and_dismissed() {
		$admin  = ActionScheduler_AdminView::instance();
		$option = ActionScheduler_AdminView::UNRECOGNIZED_SCHEDULE_NOTICE_OPTION;
		delete_option( $option );

		$admin->note_unrecognized_schedule_failure();
		$this->assertNotEmpty( get_option( $option ), 'Noting a failure should set the notice flag.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET['as_dismiss_unrecognized_schedule'] = '1';
		$_GET['_asnonce']                         = wp_create_nonce( 'as_dismiss_unrecognized_schedule' );

		ob_start();
		$admin->maybe_show_unrecognized_schedule_notice();
		ob_end_clean();

		$this->assertFalse( (bool) get_option( $option ), 'A valid dismissal should clear the notice flag.' );

		unset( $_GET['as_dismiss_unrecognized_schedule'], $_GET['_asnonce'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- test cleanup of simulated request state.
	}

	/**
	 * The notice renders for an administrator while the flag is set, and links to the failed actions
	 * screen.
	 */
	public function test_notice_renders_for_admin_and_links_to_failed_actions() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( ActionScheduler_AdminView::UNRECOGNIZED_SCHEDULE_NOTICE_OPTION, 1, false );

		ob_start();
		ActionScheduler_AdminView::instance()->maybe_show_unrecognized_schedule_notice();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'unrecognized class', $html );
		$this->assertStringContainsString( 'status=failed', $html );

		delete_option( ActionScheduler_AdminView::UNRECOGNIZED_SCHEDULE_NOTICE_OPTION );
	}

	/**
	 * Force-running a failed recurring action (with a still-valid schedule) must not schedule a
	 * duplicate next instance — the series already advanced when the action first ran.
	 */
	public function test_forced_run_of_recurring_failed_action_does_not_reschedule() {
		$hook = 'as_test_force_recurring';
		add_action( $hook, '__return_true' );

		$store  = new ActionScheduler_DBStore();
		$runner = ActionScheduler_Mocker::get_queue_runner( $store );

		$schedule  = new ActionScheduler_IntervalSchedule( as_get_datetime_object( '1 hour ago' ), HOUR_IN_SECONDS );
		$action    = new ActionScheduler_Action( $hook, array(), $schedule );
		$action_id = $store->save_action( $action );
		$store->mark_failure( $action_id );

		$pending_query = array(
			'hook'   => $hook,
			'status' => ActionScheduler_Store::STATUS_PENDING,
		);
		$this->assertSame( 0, (int) $store->query_actions( $pending_query, 'count' ) );

		$runner->force_run_action( $action_id, 'Test' );

		$this->assertSame(
			0,
			(int) $store->query_actions( $pending_query, 'count' ),
			'A forced run of a failed recurring action must not schedule a duplicate next instance.'
		);

		remove_all_actions( $hook );
	}

	/**
	 * Build a list table instance for tests, loading the WP_List_Table base if the admin context that
	 * normally provides it has not been loaded.
	 *
	 * @param ActionScheduler_Store|null       $store  Store to use.
	 * @param ActionScheduler_QueueRunner|null $runner Runner to use.
	 * @return ActionScheduler_ListTable
	 */
	private function make_list_table( $store = null, $runner = null ) {
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		$store  = $store ? $store : new ActionScheduler_DBStore();
		$runner = $runner ? $runner : ActionScheduler_Mocker::get_queue_runner( $store );

		return new ActionScheduler_ListTable( $store, ActionScheduler_Logger::instance(), $runner );
	}
}
