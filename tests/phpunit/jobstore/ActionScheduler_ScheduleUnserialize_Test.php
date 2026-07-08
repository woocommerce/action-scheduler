<?php

/**
 * Battle tests for the hardening of schedule deserialization.
 *
 * The supporting fixtures (ActionScheduler_Test_Evil_Gadget and
 * ActionScheduler_Test_Custom_Schedule) live in tests/phpunit/helpers and are loaded by the
 * test bootstrap.
 *
 * @see https://github.com/woocommerce/action-scheduler/issues/1318
 *
 * @group tables
 */
class ActionScheduler_ScheduleUnserialize_Test extends ActionScheduler_UnitTestCase {

	/**
	 * Legacy CronSchedule blob produced by Action Scheduler < this change, containing the full
	 * CronExpression object graph (CronExpression, CronExpression_FieldFactory and the field
	 * objects). Old rows in the wild look exactly like this and must keep working.
	 *
	 * Captured from trunk for "0 0 * * *" scheduled at 2026-01-01 00:00:00 UTC (ts 1767225600).
	 * Base64-encoded because the serialized form contains NUL bytes (protected/private property
	 * name markers) that cannot be represented safely as a literal PHP string.
	 *
	 * @var string
	 */
	const LEGACY_CRON_BLOB_B64 = 'TzoyODoiQWN0aW9uU2NoZWR1bGVyX0Nyb25TY2hlZHVsZSI6NTp7czoyMjoiACoAc2NoZWR1bGVkX3RpbWVzdGFtcCI7aToxNzY3MjI1NjAwO3M6MTg6IgAqAGZpcnN0X3RpbWVzdGFtcCI7aToxNzY3MjI1NjAwO3M6MTM6IgAqAHJlY3VycmVuY2UiO086MTQ6IkNyb25FeHByZXNzaW9uIjoyOntzOjI1OiIAQ3JvbkV4cHJlc3Npb24AY3JvblBhcnRzIjthOjU6e2k6MDtzOjE6IjAiO2k6MTtzOjE6IjAiO2k6MjtzOjE6IioiO2k6MztzOjE6IioiO2k6NDtzOjE6IioiO31zOjI4OiIAQ3JvbkV4cHJlc3Npb24AZmllbGRGYWN0b3J5IjtPOjI3OiJDcm9uRXhwcmVzc2lvbl9GaWVsZEZhY3RvcnkiOjE6e3M6MzU6IgBDcm9uRXhwcmVzc2lvbl9GaWVsZEZhY3RvcnkAZmllbGRzIjthOjU6e2k6MDtPOjI3OiJDcm9uRXhwcmVzc2lvbl9NaW51dGVzRmllbGQiOjA6e31pOjE7TzoyNToiQ3JvbkV4cHJlc3Npb25fSG91cnNGaWVsZCI6MDp7fWk6MjtPOjMwOiJDcm9uRXhwcmVzc2lvbl9EYXlPZk1vbnRoRmllbGQiOjA6e31pOjM7TzoyNToiQ3JvbkV4cHJlc3Npb25fTW9udGhGaWVsZCI6MDp7fWk6NDtPOjI5OiJDcm9uRXhwcmVzc2lvbl9EYXlPZldlZWtGaWVsZCI6MDp7fX19fXM6NDU6IgBBY3Rpb25TY2hlZHVsZXJfQ3JvblNjaGVkdWxlAHN0YXJ0X3RpbWVzdGFtcCI7aToxNzY3MjI1NjAwO3M6MzQ6IgBBY3Rpb25TY2hlZHVsZXJfQ3JvblNjaGVkdWxlAGNyb24iO3I6NDt9';

	public function setUp(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->actionscheduler_actions}" );
		ActionScheduler_Test_Evil_Gadget::$fired = false;
		parent::setUp();
	}

	/**
	 * Persist a real action, then overwrite its stored schedule blob with an arbitrary string,
	 * simulating an attacker (or corruption) tampering with the serialized column.
	 *
	 * @param string $schedule_blob Raw value to store in the schedule column.
	 * @return int Action ID.
	 */
	private function store_action_with_raw_schedule( $schedule_blob ) {
		global $wpdb;

		$store     = new ActionScheduler_DBStore();
		$action    = new ActionScheduler_Action(
			ActionScheduler_Callbacks::HOOK_WITH_CALLBACK,
			array(),
			new ActionScheduler_SimpleSchedule( as_get_datetime_object() )
		);
		$action_id = $store->save_action( $action );

		$wpdb->update(
			$wpdb->actionscheduler_actions,
			array( 'schedule' => $schedule_blob ),
			array( 'action_id' => $action_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $action_id;
	}

	/**
	 * THE VULNERABILITY. A tampered schedule blob referencing an arbitrary class must never
	 * cause that class to be instantiated during deserialization.
	 *
	 * On trunk this test is RED: unserialize() instantiates the gadget and runs its __wakeup().
	 */
	public function test_arbitrary_class_is_not_instantiated_from_schedule_blob() {
		$malicious = serialize( new ActionScheduler_Test_Evil_Gadget() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$action_id = $this->store_action_with_raw_schedule( $malicious );

		$store = new ActionScheduler_DBStore();

		// Fetching may legitimately throw/return a null action for an unusable schedule; that is
		// fine. What must never happen is the gadget's __wakeup() side effect firing.
		try {
			$store->fetch_action( $action_id );
		} catch ( Exception $e ) {
			unset( $e ); // Rejecting the action is an acceptable outcome; we only assert on the side effect.
		}

		$this->assertFalse(
			ActionScheduler_Test_Evil_Gadget::$fired,
			'Deserializing a schedule blob instantiated an arbitrary class (object injection).'
		);
	}

	/**
	 * A gadget nested INSIDE an otherwise-valid schedule wrapper must also be blocked. This guards
	 * against smuggling a gadget in via the recurrence/property of a legitimate schedule class.
	 */
	public function test_arbitrary_class_nested_in_valid_schedule_is_not_instantiated() {
		$nul       = chr( 0 );
		$prop_ts   = $nul . '*' . $nul . 'scheduled_timestamp'; // Protected property marker.
		$prop_rec  = $nul . '*' . $nul . 'recurrence';          // Protected property marker.
		$gadget    = 'ActionScheduler_Test_Evil_Gadget';
		$container = 'ActionScheduler_IntervalSchedule';
		$blob      = 'O:' . strlen( $container ) . ':"' . $container . '":2:{'
			. 's:' . strlen( $prop_ts ) . ':"' . $prop_ts . '";i:' . ( time() + HOUR_IN_SECONDS ) . ';'
			. 's:' . strlen( $prop_rec ) . ':"' . $prop_rec . '";'
			. 'O:' . strlen( $gadget ) . ':"' . $gadget . '":0:{}'
			. '}';
		$action_id = $this->store_action_with_raw_schedule( $blob );

		$store = new ActionScheduler_DBStore();
		try {
			$store->fetch_action( $action_id );
		} catch ( Exception $e ) {
			unset( $e ); // Rejecting the action is an acceptable outcome; we only assert on the side effect.
		}

		$this->assertFalse(
			ActionScheduler_Test_Evil_Gadget::$fired,
			'A gadget nested inside a valid schedule wrapper was instantiated.'
		);
	}

	/**
	 * A self-referential blob must not send the class-name walk into infinite recursion.
	 *
	 * PHP's `r:` references let a tampered blob decode into a cyclic object graph. Before the cycle
	 * guard, walking it recursed until the stack overflowed (a DoS). The store must instead return.
	 */
	public function test_self_referential_blob_is_handled_without_infinite_recursion() {
		$nul       = chr( 0 );
		$prop_rec  = $nul . '*' . $nul . 'recurrence'; // Protected property marker.
		$container = 'ActionScheduler_IntervalSchedule';
		// The single property `recurrence` references value #1 — the object itself — forming a cycle.
		$blob      = 'O:' . strlen( $container ) . ':"' . $container . '":1:{'
			. 's:' . strlen( $prop_rec ) . ':"' . $prop_rec . '";r:1;}';
		$action_id = $this->store_action_with_raw_schedule( $blob );

		$store   = new ActionScheduler_DBStore();
		$fetched = null;
		try {
			$fetched = $store->fetch_action( $action_id );
		} catch ( Exception $e ) {
			unset( $e ); // Acceptable; the point is that we return rather than exhaust the stack.
		}

		// Reaching this line at all proves the walk terminated. fetch_action always yields an object.
		$this->assertNotNull( $fetched, 'A self-referential schedule blob did not resolve to an action.' );
	}

	/**
	 * The happy path must keep working: a simple schedule round-trips through the store unchanged.
	 */
	public function test_simple_schedule_round_trips() {
		$time      = as_get_datetime_object( '2026-03-04 05:06:07' );
		$store     = new ActionScheduler_DBStore();
		$action    = new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array(), new ActionScheduler_SimpleSchedule( $time ) );
		$action_id = $store->save_action( $action );

		$fetched  = $store->fetch_action( $action_id );
		$schedule = $fetched->get_schedule();

		$this->assertInstanceOf( 'ActionScheduler_SimpleSchedule', $schedule );
		$this->assertEquals( $time->getTimestamp(), $schedule->get_date()->getTimestamp() );
	}

	/**
	 * Cron schedules must round-trip, including their recurrence.
	 */
	public function test_cron_schedule_round_trips() {
		$time      = as_get_datetime_object( '2026-01-01 00:00:00' );
		$store     = new ActionScheduler_DBStore();
		$action    = new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array(), new ActionScheduler_CronSchedule( $time, '0 0 * * *' ) );
		$action_id = $store->save_action( $action );

		$fetched  = $store->fetch_action( $action_id );
		$schedule = $fetched->get_schedule();

		$this->assertInstanceOf( 'ActionScheduler_CronSchedule', $schedule );
		$this->assertEquals( '0 0 * * *', $schedule->get_recurrence() );
	}

	/**
	 * Existing rows serialized before this change still contain the full CronExpression object
	 * graph. Those must continue to deserialize into a working schedule.
	 */
	public function test_legacy_cron_blob_still_unserializes() {
		$action_id = $this->store_action_with_raw_schedule( base64_decode( self::LEGACY_CRON_BLOB_B64 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		$store    = new ActionScheduler_DBStore();
		$fetched  = $store->fetch_action( $action_id );
		$schedule = $fetched->get_schedule();

		$this->assertInstanceOf( 'ActionScheduler_CronSchedule', $schedule );
		$this->assertEquals( '0 0 * * *', $schedule->get_recurrence() );
	}

	/**
	 * A third party schedule class (implementing ActionScheduler_Schedule, defined outside AS)
	 * must keep working without the vendor registering anything.
	 */
	public function test_third_party_schedule_class_round_trips() {
		$timestamp = time() + DAY_IN_SECONDS;
		$blob      = serialize( new ActionScheduler_Test_Custom_Schedule( $timestamp ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$action_id = $this->store_action_with_raw_schedule( $blob );

		$store    = new ActionScheduler_DBStore();
		$fetched  = $store->fetch_action( $action_id );
		$schedule = $fetched->get_schedule();

		$this->assertInstanceOf( 'ActionScheduler_Test_Custom_Schedule', $schedule );
		$this->assertEquals( $timestamp, $schedule->next()->getTimestamp() );
	}

	/**
	 * The same protection must cover the legacy post-based store, whose schedule lives in post meta.
	 *
	 * On trunk this is RED: get_post_meta() runs the tampered blob through maybe_unserialize().
	 */
	public function test_wp_post_store_does_not_instantiate_arbitrary_class() {
		$store     = new ActionScheduler_wpPostStore();
		$action    = new ActionScheduler_Action(
			ActionScheduler_Callbacks::HOOK_WITH_CALLBACK,
			array(),
			new ActionScheduler_SimpleSchedule( as_get_datetime_object() )
		);
		$action_id = $store->save_action( $action );

		// Tamper the stored schedule meta with a gadget blob, bypassing the meta API's serialization.
		global $wpdb;
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$wpdb->update(
			$wpdb->postmeta,
			array( 'meta_value' => serialize( new ActionScheduler_Test_Evil_Gadget() ) ),
			array(
				'post_id'  => $action_id,
				'meta_key' => ActionScheduler_wpPostStore::SCHEDULE_META_KEY,
			),
			array( '%s' ),
			array( '%d', '%s' )
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		clean_post_cache( $action_id );
		wp_cache_delete( $action_id, 'post_meta' );

		try {
			$store->fetch_action( $action_id );
		} catch ( Exception $e ) {
			unset( $e ); // Rejecting the action is an acceptable outcome; we only assert on the side effect.
		}

		$this->assertFalse(
			ActionScheduler_Test_Evil_Gadget::$fired,
			'The post-based store instantiated an arbitrary class from a schedule blob.'
		);
	}

	/**
	 * The post-based store must still round-trip legitimate schedules after hardening.
	 */
	public function test_wp_post_store_round_trips_valid_schedule() {
		$time      = as_get_datetime_object( '2026-05-06 07:08:09' );
		$store     = new ActionScheduler_wpPostStore();
		$action    = new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array(), new ActionScheduler_SimpleSchedule( $time ) );
		$action_id = $store->save_action( $action );

		$fetched  = $store->fetch_action( $action_id );
		$schedule = $fetched->get_schedule();

		$this->assertInstanceOf( 'ActionScheduler_SimpleSchedule', $schedule );
		$this->assertEquals( $time->getTimestamp(), $schedule->get_date()->getTimestamp() );
	}
}
