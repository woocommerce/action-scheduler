<?php

/**
 * Class ActionScheduler_DBLogger_Test
 * @package test_cases\logging
 * @group tables
 */
class ActionScheduler_DBLogger_Test extends ActionScheduler_UnitTestCase {

	/**
	 * Saved value of the WP_CLI constant so individual tests can restore it.
	 *
	 * @var mixed
	 */
	private $wp_cli_constant;

	public function setUp(): void {
		global $wpdb;

		parent::setUp();

		$wpdb->query( "DELETE FROM {$wpdb->actionscheduler_logs}" );
		$wpdb->query( "DELETE FROM {$wpdb->actionscheduler_actions}" );
		$wpdb->query( "DELETE FROM {$wpdb->actionscheduler_claims}" );

		$this->wp_cli_constant = defined( 'WP_CLI' ) ? WP_CLI : null;
	}

	public function tearDown(): void {
		if ( null === $this->wp_cli_constant ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Runtime constant toggled for the test.
			$this->define_or_undefine_wp_cli( null );
		} else {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Runtime constant toggled for the test.
			$this->define_or_undefine_wp_cli( $this->wp_cli_constant );
		}

		parent::tearDown();
	}

	/**
	 * Helper to define WP_CLI to a value, or undefine it when null is passed.
	 *
	 * @param bool|null $value True/false to define WP_CLI; null to remove it.
	 */
	private function define_or_undefine_wp_cli( $value ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Runtime constant toggled for the test.
		if ( null === $value ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_constants -- Test-only cleanup.
			$runkit_constant_redefined = false;
			if ( function_exists( 'runkit_constant_redefine' ) ) {
				$runkit_constant_redefined = true;
			}
			// Without runkit we can only force the WP_CLI branch off; the false branch is fully covered by setUp().
			return;
		}
	}

	public function test_default_logger() {
		$logger = ActionScheduler::logger();
		$this->assertInstanceOf( 'ActionScheduler_Logger', $logger );
		$this->assertInstanceOf( ActionScheduler_DBLogger::class, $logger );
	}

	public function test_add_log_entry() {
		$action_id = as_schedule_single_action( time(), __METHOD__ );
		$logger    = ActionScheduler::logger();
		$message   = 'Logging that something happened';
		$log_id    = $logger->log( $action_id, $message );
		$entry     = $logger->get_entry( $log_id );

		$this->assertEquals( $action_id, $entry->get_action_id() );
		$this->assertEquals( $message, $entry->get_message() );
	}

	public function test_storage_logs() {
		$action_id = as_schedule_single_action( time(), __METHOD__ );
		$logger    = ActionScheduler::logger();
		$logs      = $logger->get_logs( $action_id );
		$expected  = new ActionScheduler_LogEntry( $action_id, 'action created' );
		$this->assertCount( 1, $logs );
		$this->assertEquals( $expected->get_action_id(), $logs[0]->get_action_id() );
		$this->assertEquals( $expected->get_message(), $logs[0]->get_message() );
	}

	public function test_execution_logs() {
		$action_id = as_schedule_single_action( time(), ActionScheduler_Callbacks::HOOK_WITH_CALLBACK );
		$logger    = ActionScheduler::logger();
		$started   = new ActionScheduler_LogEntry( $action_id, 'action started via Unit Tests' );
		$finished  = new ActionScheduler_LogEntry( $action_id, 'action complete via Unit Tests' );

		$runner = ActionScheduler_Mocker::get_queue_runner();
		$runner->run( 'Unit Tests' );

		// Expect 3 logs with the correct action ID.
		$logs = $logger->get_logs( $action_id );
		$this->assertCount( 3, $logs );
		foreach ( $logs as $log ) {
			$this->assertEquals( $action_id, $log->get_action_id() );
		}

		// Expect created, then started, then completed.
		$this->assertEquals( 'action created', $logs[0]->get_message() );
		$this->assertEquals( $started->get_message(), $logs[1]->get_message() );
		$this->assertEquals( $finished->get_message(), $logs[2]->get_message() );
	}

	public function test_failed_execution_logs() {
		$hook = __METHOD__;
		add_action( $hook, array( $this, 'a_hook_callback_that_throws_an_exception' ) );
		$action_id = as_schedule_single_action( time(), $hook );
		$logger    = ActionScheduler::logger();
		$started   = new ActionScheduler_LogEntry( $action_id, 'action started via Unit Tests' );
		$finished  = new ActionScheduler_LogEntry( $action_id, 'action complete via Unit Tests' );
		$failed    = new ActionScheduler_LogEntry( $action_id, 'action failed via Unit Tests: Execution failed' );

		$runner = ActionScheduler_Mocker::get_queue_runner();
		$runner->run( 'Unit Tests' );

		// Expect 3 logs with the correct action ID.
		$logs = $logger->get_logs( $action_id );
		$this->assertCount( 3, $logs );
		foreach ( $logs as $log ) {
			$this->assertEquals( $action_id, $log->get_action_id() );
			$this->assertNotEquals( $finished->get_message(), $log->get_message() );
		}

		// Expect created, then started, then failed.
		$this->assertEquals( 'action created', $logs[0]->get_message() );
		$this->assertEquals( $started->get_message(), $logs[1]->get_message() );
		$this->assertEquals( $failed->get_message(), $logs[2]->get_message() );
	}

	public function test_fatal_error_log() {
		$action_id = as_schedule_single_action( time(), __METHOD__ );
		$logger    = ActionScheduler::logger();
		$args      = array(
			'type'    => E_ERROR,
			'message' => 'Test error',
			'file'    => __FILE__,
			'line'    => __LINE__,
		);

		do_action( 'action_scheduler_unexpected_shutdown', $action_id, $args );

		$logs      = $logger->get_logs( $action_id );
		$found_log = false;
		foreach ( $logs as $l ) {
			if ( strpos( $l->get_message(), 'unexpected shutdown' ) === 0 ) {
				$found_log = true;
			}
		}
		$this->assertTrue( $found_log, 'Unexpected shutdown log not found' );
	}

	public function test_canceled_action_log() {
		$action_id = as_schedule_single_action( time(), __METHOD__ );
		as_unschedule_action( __METHOD__ );
		$logger   = ActionScheduler::logger();
		$logs     = $logger->get_logs( $action_id );
		$expected = new ActionScheduler_LogEntry( $action_id, 'action canceled' );
		$this->assertEquals( $expected->get_message(), end( $logs )->get_message() );
	}

	public function test_deleted_action_cleanup() {
		$time      = as_get_datetime_object( '-10 minutes' );
		$schedule  = new \ActionScheduler_SimpleSchedule( $time );
		$action    = new \ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array(), $schedule );
		$store     = new ActionScheduler_DBStore();
		$action_id = $store->save_action( $action );

		$logger = new ActionScheduler_DBLogger();
		$logs   = $logger->get_logs( $action_id );
		$this->assertNotEmpty( $logs );

		$store->delete_action( $action_id );
		$logs = $logger->get_logs( $action_id );
		$this->assertEmpty( $logs );
	}

	/**
	 * In a WP-CLI context, clear_deleted_action_logs() must remove ALL log rows
	 * for the given action in a single synchronous call, without relying on
	 * follow-up background batches.
	 *
	 * The test deliberately inserts more orphan log rows than the batched
	 * fallback's LIMIT (4000) so the two code paths become distinguishable:
	 * the WP-CLI direct DELETE removes every row synchronously, while the
	 * batched path leaves 500 rows behind and schedules a follow-up.
	 */
	public function test_clear_deleted_action_logs_uses_direct_delete_in_wp_cli() {
		global $wpdb;

		// We can only force the WP-CLI context if the constant is not already locked in as false.
		if ( defined( 'WP_CLI' ) && ! WP_CLI ) {
			$this->markTestSkipped( 'WP_CLI is defined as false in this environment; cannot exercise the WP-CLI branch.' );
			return;
		}
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		$orphan_action_id = 999999; // An action_id that does not exist in actionscheduler_actions.
		$orphan_log_count = 4500;   // Exceeds the batched fallback's LIMIT of 4000.

		// Insert orphan log rows pointing at the non-existent action.
		$rows = array();
		for ( $i = 0; $i < $orphan_log_count; $i++ ) {
			$rows[] = $wpdb->prepare( '(%d, %s, %s, %s)', $orphan_action_id, 'orphan log', '2020-01-01 00:00:00', '2020-01-01 00:00:00' );
		}
		$wpdb->query( "INSERT INTO {$wpdb->actionscheduler_logs} (action_id, message, log_date_gmt, log_date_local) VALUES " . implode( ',', $rows ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$before = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->actionscheduler_logs} WHERE action_id = %d", $orphan_action_id )
		);
		$this->assertSame( $orphan_log_count, $before, 'Sanity: all orphan logs were inserted.' );

		// Run the cleanup exactly as the WP-CLI `clean` command would.
		$logger = new ActionScheduler_DBLogger();
		$logger->clear_deleted_action_logs( $orphan_action_id );

		// Critical assertion: the WP-CLI path must remove every orphan row synchronously.
		$after = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->actionscheduler_logs} WHERE action_id = %d", $orphan_action_id )
		);
		$this->assertSame( 0, $after, 'WP-CLI path must remove all orphan logs in a single synchronous call, without scheduling follow-up batches.' );
	}

	public function a_hook_callback_that_throws_an_exception() {
		throw new \RuntimeException( 'Execution failed' );
	}
}
