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

	/**
	 * A lock can be acquired when none is held.
	 *
	 * Covers: set() INSERT branch → success → cache populated; is_locked() false → true transition.
	 */
	public function test_lock_can_be_acquired() {
		$lock      = ActionScheduler::lock();
		$lock_type = uniqid( 'lock_test_', true );

		$this->assertFalse( $lock->is_locked( $lock_type ), 'Lock should not be held before acquisition.' );

		$acquired = $lock->set( $lock_type );

		$this->assertTrue( $acquired, 'set() should return true when no lock is held.' );
		$this->assertTrue( $lock->is_locked( $lock_type ), 'Lock should be held immediately after acquisition.' );
		$this->assertGreaterThan( time(), $lock->get_expiration( $lock_type ), 'Lock expiration should be in the future.' );
	}

	/**
	 * A lock cannot be re-acquired while the existing one is still active.
	 *
	 * Covers: set() branch where expiration > $now → returns false without touching DB; get_existing_lock() cache hit branch.
	 */
	public function test_lock_cannot_be_acquired_while_active() {
		$lock      = ActionScheduler::lock();
		$lock_type = uniqid( 'lock_test_', true );
		$lock_key  = 'action_scheduler_lock_' . $lock_type;

		$first_acquisition = $lock->set( $lock_type );
		$this->assertTrue( $first_acquisition, 'First set() should succeed when no lock is held.' );
		$cached_after_first = wp_cache_get( $lock_key, 'action_scheduler_locks' );
		$this->assertNotFalse( $cached_after_first, 'Lock value should be present in object cache after acquisition.' );

		$second_acquisition = $lock->set( $lock_type );
		$this->assertFalse( $second_acquisition, 'Second set() should fail while the lock is still active.' );
		$cached_after_second = wp_cache_get( $lock_key, 'action_scheduler_locks' );
		$this->assertSame( $cached_after_first, $cached_after_second, 'Cache entry should be unchanged after a failed re-acquisition attempt.' );

		$this->assertTrue( $lock->is_locked( $lock_type ), 'Lock should remain held after a failed re-acquisition attempt.' );
	}

	/**
	 * When the cache is evicted for a live lock, get_existing_lock() reads from DB and re-populates the cache.
	 *
	 * Covers: get_existing_lock() cache miss → DB hit → TTL > 0 → wp_cache_set branch.
	 */
	public function test_cache_miss_repopulates_cache_for_an_active_lock() {
		$lock      = ActionScheduler::lock();
		$lock_type = uniqid( 'lock_test_', true );
		$lock_key  = 'action_scheduler_lock_' . $lock_type;

		$lock->set( $lock_type );

		// Evict the cache entry to force a DB read on the next call.
		wp_cache_delete( $lock_key, 'action_scheduler_locks' );
		$this->assertFalse( wp_cache_get( $lock_key, 'action_scheduler_locks' ), 'Cache should be empty after explicit eviction.' );

		// is_locked() must hit the DB, confirm the lock is live, and re-populate the cache.
		$this->assertTrue( $lock->is_locked( $lock_type ), 'Lock should still be held after cache eviction.' );
		$this->assertNotFalse( wp_cache_get( $lock_key, 'action_scheduler_locks' ), 'Cache should be re-populated after reading a live lock from the DB.' );
	}
}
