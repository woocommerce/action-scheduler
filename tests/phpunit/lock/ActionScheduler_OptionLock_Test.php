<?php

/**
 * Class ActionScheduler_Lock_Test
 * @package test_cases\lock
 */
class ActionScheduler_OptionLock_Test extends ActionScheduler_UnitTestCase {
	public function test_instance() {
		$lock = ActionScheduler::lock();
		$this->assertInstanceOf( 'ActionScheduler_Lock', $lock );
		$this->assertInstanceOf( 'ActionScheduler_OptionLock', $lock );
	}

	public function test_is_locked() {
		$lock      = ActionScheduler::lock();
		$lock_type = md5( wp_rand() );

		$this->assertFalse( $lock->is_locked( $lock_type ) );

		$lock->set( $lock_type );
		$this->assertTrue( $lock->is_locked( $lock_type ) );
	}

	public function test_set() {
		$lock      = ActionScheduler::lock();
		$lock_type = md5( wp_rand() );

		$lock->set( $lock_type );
		$this->assertTrue( $lock->is_locked( $lock_type ) );
	}

	public function test_get_expiration() {
		$lock      = ActionScheduler::lock();
		$lock_type = md5( wp_rand() );

		$lock->set( $lock_type );

		$expiration   = $lock->get_expiration( $lock_type );
		$current_time = time();

		$this->assertGreaterThanOrEqual( 0, $expiration );
		$this->assertGreaterThan( $current_time, $expiration );
		$this->assertLessThan( $current_time + MINUTE_IN_SECONDS + 1, $expiration );
	}

	/**
	 * A call to `ActionScheduler::lock()->set()` should fail if the lock is already held (ie, by another process).
	 *
	 * @return void
	 */
	public function test_lock_resists_race_conditions() {
		global $wpdb;

		$lock = ActionScheduler::lock();
		$type = md5( wp_rand() );

		// Approximate conditions in which a concurrently executing request manages to set (and obtain) the lock
		// immediately before the current request can do so.
		$simulate_concurrent_claim = function ( $query ) use ( $lock, $type ) {
			static $executed = false;

			if ( ! $executed && false !== strpos( $query, 'action_scheduler_lock_' ) && 0 === strpos( $query, 'INSERT INTO' ) ) {
				$executed = true;
				$lock->set( $type );
			}

			return $query;
		};

		add_filter( 'query', $simulate_concurrent_claim );
		$wpdb->suppress_errors( true );
		$this->assertFalse( $lock->is_locked( $type ), 'Initially, the lock is not held' );
		$this->assertFalse( $lock->set( $type ), 'The lock was not obtained, because another process already claimed it.' );
		$wpdb->suppress_errors( false );
		remove_filter( 'query', $simulate_concurrent_claim );
	}

	/**
	 * If the lock option already exists but holds an empty string, `set()` should still be able to
	 * obtain the lock.
	 *
	 * This corrupted state (an existing row with an empty `option_value`) can be left behind by older
	 * versions of Action Scheduler. It must be recovered by updating the existing row, rather than by
	 * attempting to insert a duplicate row (which would fail, leaving the lock permanently stuck).
	 *
	 * @return void
	 */
	public function test_set_recovers_from_empty_lock_value() {
		global $wpdb;

		$lock     = ActionScheduler::lock();
		$type     = md5( wp_rand() );
		$lock_key = 'action_scheduler_lock_' . $type;

		// Simulate the corrupted state: an existing lock option whose value is an empty string.
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => $lock_key,
				'option_value' => '',
				'autoload'     => 'no',
			)
		);

		// An empty lock value does not represent a held lock.
		$this->assertFalse( $lock->is_locked( $type ), 'An empty lock value is not treated as a held lock.' );

		// Setting the lock should succeed by updating (healing) the empty row, not by inserting a duplicate.
		$this->assertTrue( $lock->set( $type ), 'The lock is obtained despite a pre-existing empty lock value.' );
		$this->assertTrue( $lock->is_locked( $type ), 'The lock is held after recovering from the empty value.' );

		// The stored value should now be a valid lock value with a future expiration timestamp.
		$this->assertGreaterThan( time(), $lock->get_expiration( $type ), 'The recovered lock has a future expiration.' );
	}
}
