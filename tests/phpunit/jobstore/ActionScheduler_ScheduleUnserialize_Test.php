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

	public function tearDown(): void {
		// These tests register filters/actions on the deserializer's hooks; clear them so nothing leaks
		// into later tests running in the same process.
		remove_all_filters( 'action_scheduler_allowed_nested_schedule_classes' );
		remove_all_filters( 'action_scheduler_enforce_schedule_allowed_classes' );
		remove_all_actions( 'action_scheduler_unexpected_schedule_class' );
		remove_all_actions( 'action_scheduler_failed_fetch_action' );
		parent::tearDown();
	}

	/**
	 * A serialized blob for a valid schedule (ActionScheduler_IntervalSchedule) that smuggles a gadget
	 * in as its (protected) recurrence property. Hand-crafted because __sleep() would otherwise drop a
	 * non-whitelisted property.
	 *
	 * @return string
	 */
	private function nested_gadget_blob() {
		$nul       = chr( 0 );
		$prop_ts   = $nul . '*' . $nul . 'scheduled_timestamp'; // Protected property marker.
		$prop_rec  = $nul . '*' . $nul . 'recurrence';          // Protected property marker.
		$gadget    = 'ActionScheduler_Test_Evil_Gadget';
		$container = 'ActionScheduler_IntervalSchedule';

		return 'O:' . strlen( $container ) . ':"' . $container . '":2:{'
			. 's:' . strlen( $prop_ts ) . ':"' . $prop_ts . '";i:' . ( time() + HOUR_IN_SECONDS ) . ';'
			. 's:' . strlen( $prop_rec ) . ':"' . $prop_rec . '";'
			. 'O:' . strlen( $gadget ) . ':"' . $gadget . '":0:{}'
			. '}';
	}

	/**
	 * Shadow mode ("report only") must NEVER let a bare top-level gadget be instantiated. Only an
	 * unexpected *nested* class inside an otherwise-valid schedule may pass through in shadow mode.
	 */
	public function test_shadow_mode_still_blocks_top_level_gadget() {
		add_filter( 'action_scheduler_enforce_schedule_allowed_classes', '__return_false' );

		$blob   = serialize( new ActionScheduler_Test_Evil_Gadget() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$result = ActionScheduler_ScheduleDeserializer::unserialize( $blob );

		$this->assertFalse( $result, 'A top-level gadget was not rejected in shadow mode.' );
		$this->assertFalse(
			ActionScheduler_Test_Evil_Gadget::$fired,
			'A top-level gadget was instantiated in shadow mode.'
		);
	}

	/**
	 * Shadow mode DOES let an unexpected nested class through: this is its documented purpose (keep an
	 * otherwise-legitimate action working while an allow-list is being tuned). The tradeoff — and the
	 * reason enforcement is the default — is that the nested class is instantiated.
	 */
	public function test_shadow_mode_allows_unexpected_nested_class_through() {
		add_filter( 'action_scheduler_enforce_schedule_allowed_classes', '__return_false' );

		$result = ActionScheduler_ScheduleDeserializer::unserialize( $this->nested_gadget_blob() );

		$this->assertInstanceOf(
			'ActionScheduler_IntervalSchedule',
			$result,
			'Shadow mode did not pass a valid schedule with an unexpected nested class through.'
		);
		$this->assertTrue(
			ActionScheduler_Test_Evil_Gadget::$fired,
			'Shadow mode is report-only for nested classes, so the nested class is expected to run.'
		);
	}

	/**
	 * A rejection must surface the action_scheduler_unexpected_schedule_class action for observability,
	 * reporting the offending class, the outer class, and that the blob was rejected.
	 */
	public function test_unexpected_class_action_fires_on_rejection() {
		$captured = array();
		add_action(
			'action_scheduler_unexpected_schedule_class',
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			function ( $offending, $outer, $unexpected, $rejected ) use ( &$captured ) {
				$captured = compact( 'offending', 'outer', 'unexpected', 'rejected' );
			},
			10,
			4
		);

		$blob   = serialize( new ActionScheduler_Test_Evil_Gadget() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$result = ActionScheduler_ScheduleDeserializer::unserialize( $blob );

		$this->assertFalse( $result );
		$this->assertSame( 'ActionScheduler_Test_Evil_Gadget', $captured['offending'] );
		$this->assertSame( 'ActionScheduler_Test_Evil_Gadget', $captured['outer'] );
		$this->assertTrue( $captured['rejected'] );
	}

	/**
	 * The action_scheduler_allowed_nested_schedule_classes filter must be honored, and — because it is
	 * resolved per call rather than cached for the process — a class allow-listed only for the second
	 * call is rejected on the first and accepted on the second.
	 */
	public function test_nested_allow_list_filter_is_honored_per_call() {
		$blob = serialize( new ActionScheduler_Test_Custom_Schedule_With_Property( time() + DAY_IN_SECONDS, new ActionScheduler_Test_Schedule_Helper( 42 ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

		// First call, no filter: the helper class is unexpected, so the blob yields the unrecognized
		// placeholder (a valid schedule referencing an unknown class is recoverable, not corrupt).
		$this->assertInstanceOf(
			'ActionScheduler_UnrecognizedSchedule',
			ActionScheduler_ScheduleDeserializer::unserialize( $blob ),
			'An un-allow-listed nested support class should yield an unrecognized-schedule placeholder.'
		);

		// Second call, with the helper allow-listed via the filter: the blob is accepted. This also
		// proves the first, filter-less call did not cache a stale allow-list for the process.
		add_filter(
			'action_scheduler_allowed_nested_schedule_classes',
			function ( $classes ) {
				$classes[] = 'ActionScheduler_Test_Schedule_Helper';
				return $classes;
			}
		);

		$this->assertInstanceOf(
			'ActionScheduler_Test_Custom_Schedule_With_Property',
			ActionScheduler_ScheduleDeserializer::unserialize( $blob ),
			'A nested class allow-listed via the filter should be accepted.'
		);
	}

	/**
	 * A third party schedule that keeps built-in date/time value objects (DateTime, DateTimeImmutable,
	 * DateTimeZone, DateInterval) as properties must round-trip with no configuration — those classes
	 * are on the default nested allow-list.
	 */
	public function test_third_party_schedule_nesting_datetime_objects_round_trips() {
		$timestamp = time() + DAY_IN_SECONDS;
		$blob      = serialize( new ActionScheduler_Test_Custom_Schedule_With_Property( $timestamp ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

		$result = ActionScheduler_ScheduleDeserializer::unserialize( $blob );

		$this->assertInstanceOf( 'ActionScheduler_Test_Custom_Schedule_With_Property', $result );
		$this->assertEquals( $timestamp, $result->next()->getTimestamp() );
	}

	/**
	 * A pathologically deep object graph must be rejected rather than walked into a stack overflow.
	 * (Cyclic graphs are covered separately; this covers sheer depth beyond MAX_GRAPH_DEPTH.)
	 */
	public function test_excessively_deep_graph_is_rejected() {
		// A valid (third party) schedule class as the outer object so the top-level gate passes, wrapping
		// a scalar buried in ~150 nested arrays — well beyond the depth we are willing to vet.
		$class = 'ActionScheduler_Test_Custom_Schedule_With_Property';
		$deep  = 'i:1;';
		for ( $i = 0; $i < 150; $i++ ) {
			$deep = 'a:1:{i:0;' . $deep . '}';
		}
		$blob = 'O:' . strlen( $class ) . ':"' . $class . '":1:{s:4:"deep";' . $deep . '}';

		$this->assertFalse(
			ActionScheduler_ScheduleDeserializer::unserialize( $blob ),
			'A graph deeper than the vetting bound should be rejected.'
		);
	}

	/**
	 * The deserializer instance is reusable: invoking it for one blob must not leak walk state into the
	 * next. A rejected blob followed by a clean one must not taint the clean result.
	 */
	public function test_deserializer_instance_is_reusable() {
		$deserializer = new ActionScheduler_ScheduleDeserializer(
			array( 'ActionScheduler_Test_Custom_Schedule' ),
			array()
		);

		// First: a blob nesting a gadget (rejected to the unrecognized placeholder, populating internal
		// offender/seen state).
		$this->assertInstanceOf( 'ActionScheduler_UnrecognizedSchedule', $deserializer( $this->nested_gadget_blob() ) );

		// Then: a clean third party schedule must still deserialize correctly.
		$clean = serialize( new ActionScheduler_Test_Custom_Schedule( time() + HOUR_IN_SECONDS ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->assertInstanceOf(
			'ActionScheduler_Test_Custom_Schedule',
			$deserializer( $clean ),
			'Reusing a deserializer instance leaked state from a prior blob.'
		);
	}

	/**
	 * The constructor's nested allow-list is authoritative: a class it lists is accepted when nested,
	 * and the same blob is rejected when it is not listed.
	 */
	public function test_constructor_nested_allow_list_is_used() {
		$blob = serialize( new ActionScheduler_Test_Custom_Schedule_With_Property( time() + DAY_IN_SECONDS, new ActionScheduler_Test_Schedule_Helper( 7 ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

		$without = new ActionScheduler_ScheduleDeserializer( array(), array() );
		$this->assertInstanceOf( 'ActionScheduler_UnrecognizedSchedule', $without( $blob ), 'Nested support classes absent from the allow-list yield an unrecognized-schedule placeholder.' );

		$with = new ActionScheduler_ScheduleDeserializer(
			array(),
			array( 'DateTime', 'DateTimeImmutable', 'DateTimeZone', 'DateInterval', 'ActionScheduler_Test_Schedule_Helper' )
		);
		$this->assertInstanceOf(
			'ActionScheduler_Test_Custom_Schedule_With_Property',
			$with( $blob ),
			'A nested class present in the injected allow-list must be accepted.'
		);
	}

	/**
	 * The post-based store reads the raw schedule meta. A non-serialized meta value cannot be a schedule
	 * object, so it is left for validate_schedule() to reject rather than being deserialized — which
	 * fetch_action() surfaces as a failed fetch and a null action, not a usable schedule.
	 */
	public function test_wp_post_store_rejects_non_serialized_schedule_meta() {
		$store     = new ActionScheduler_wpPostStore();
		$action    = new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array(), new ActionScheduler_SimpleSchedule( as_get_datetime_object() ) );
		$action_id = $store->save_action( $action );

		global $wpdb;
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$wpdb->update(
			$wpdb->postmeta,
			array( 'meta_value' => 'this-is-not-serialized' ),
			array(
				'post_id'  => $action_id,
				'meta_key' => ActionScheduler_wpPostStore::SCHEDULE_META_KEY,
			),
			array( '%s' ),
			array( '%d', '%s' )
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		clean_post_cache( $action_id );
		wp_cache_delete( $action_id, 'post_meta' );

		$failed = false;
		add_action(
			'action_scheduler_failed_fetch_action',
			function () use ( &$failed ) {
				$failed = true;
			}
		);

		$fetched = $store->fetch_action( $action_id );

		$this->assertTrue( $failed, 'A non-serialized schedule meta value was not treated as an invalid action.' );
		$this->assertInstanceOf( 'ActionScheduler_NullAction', $fetched );
	}

	/**
	 * as_get_datetime_object() returns an ActionScheduler_DateTime (a DateTime subclass), so a third
	 * party schedule that stores the result of that idiomatic helper as a property nests one rather than
	 * a plain DateTime. It must be on the default nested allow-list, or such a schedule would be rejected.
	 */
	public function test_third_party_schedule_nesting_action_scheduler_datetime_round_trips() {
		// A third party schedule (protected $timestamp) holding an ActionScheduler_DateTime instead of an
		// int. Hand-crafted so the nested object is genuinely an ActionScheduler_DateTime.
		$nul   = chr( 0 );
		$prop  = $nul . '*' . $nul . 'timestamp';
		$class = 'ActionScheduler_Test_Custom_Schedule';
		$date  = serialize( as_get_datetime_object( '2026-01-02 03:04:05' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$blob  = 'O:' . strlen( $class ) . ':"' . $class . '":1:{s:' . strlen( $prop ) . ':"' . $prop . '";' . $date . '}';

		$result = ActionScheduler_ScheduleDeserializer::unserialize( $blob );

		$this->assertInstanceOf( 'ActionScheduler_Test_Custom_Schedule', $result );

		$property = new ReflectionProperty( 'ActionScheduler_Test_Custom_Schedule', 'timestamp' );
		$property->setAccessible( true );
		$this->assertInstanceOf( 'ActionScheduler_DateTime', $property->getValue( $result ) );
	}

	/**
	 * A gadget buried inside a valid third party schedule wrapper must be rejected without ever being
	 * instantiated. Unlike the core-schedule wrapper case, the outer object is itself a placeholder in
	 * the safe parse, so this exercises the walk recursing into a placeholder to find a nested one.
	 */
	public function test_gadget_nested_in_third_party_schedule_is_rejected_without_instantiation() {
		$nul    = chr( 0 );
		$prop   = $nul . '*' . $nul . 'timestamp';
		$class  = 'ActionScheduler_Test_Custom_Schedule'; // Valid third party schedule → placeholder in the safe parse.
		$gadget = serialize( new ActionScheduler_Test_Evil_Gadget() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$blob   = 'O:' . strlen( $class ) . ':"' . $class . '":1:{s:' . strlen( $prop ) . ':"' . $prop . '";' . $gadget . '}';

		$result = ActionScheduler_ScheduleDeserializer::unserialize( $blob );

		$this->assertInstanceOf( 'ActionScheduler_UnrecognizedSchedule', $result );
		$this->assertFalse(
			ActionScheduler_Test_Evil_Gadget::$fired,
			'A gadget nested in a third party schedule was instantiated.'
		);
	}

	/**
	 * A tampered blob can make an otherwise-trusted schedule class throw during deserialization — for
	 * example, a valid schedule whose `scheduled_timestamp` scalar has been replaced with an object,
	 * which the schedule's `__wakeup()` then feeds to a `DateTime` constructor. That surfaces as a
	 * `TypeError` (an `Error`, which `@` does not suppress and a `catch ( Exception )` does not catch).
	 * The deserializer must absorb it and report corrupt data, not let it escape and fatal the read.
	 */
	public function test_tampered_blob_that_makes_a_trusted_class_throw_is_reported_as_corrupt() {
		$nul   = chr( 0 );
		$prop  = $nul . '*' . $nul . 'scheduled_timestamp'; // Protected property marker.
		$class = 'ActionScheduler_IntervalSchedule';        // Trusted → instantiated (and woken) in the safe parse.
		$evil  = 'O:12:"Totally_Fake":0:{}';                // An object where a timestamp is expected.
		$blob  = 'O:' . strlen( $class ) . ':"' . $class . '":1:{s:' . strlen( $prop ) . ':"' . $prop . '";' . $evil . '}';

		// Without the guard this call throws an uncaught TypeError and this test errors instead of passing.
		$this->assertFalse(
			ActionScheduler_ScheduleDeserializer::unserialize( $blob ),
			'A tampered blob that makes a trusted class throw should be reported as corrupt (false).'
		);

		// And the store must surface it as a handled (null) action rather than fataling the read.
		$action_id = $this->store_action_with_raw_schedule( $blob );
		$store     = new ActionScheduler_DBStore();
		$fetched   = null;
		try {
			$fetched = $store->fetch_action( $action_id );
		} catch ( Exception $e ) {
			unset( $e ); // A handled InvalidActionException is acceptable; an Error would have fataled above.
		}
		$this->assertNotNull( $fetched, 'Fetching a tampered action fataled instead of being handled.' );
	}

	/**
	 * A structurally-valid schedule that merely nests an unrecognized class is recoverable: it yields an
	 * ActionScheduler_UnrecognizedSchedule placeholder (carrying the offending class names) rather than
	 * being treated as irretrievably corrupt. A structural rejection (top-level non-schedule) stays
	 * corrupt (false).
	 */
	public function test_recoverable_schedule_yields_unrecognized_placeholder_but_structural_stays_corrupt() {
		// Valid third party schedule nesting an unknown helper class -> recoverable.
		$recoverable = serialize( new ActionScheduler_Test_Custom_Schedule_With_Property( time() + DAY_IN_SECONDS, new ActionScheduler_Test_Schedule_Helper( 1 ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$schedule    = ActionScheduler_ScheduleDeserializer::unserialize( $recoverable );

		$this->assertInstanceOf( 'ActionScheduler_UnrecognizedSchedule', $schedule );
		$this->assertContains(
			'ActionScheduler_Test_Schedule_Helper',
			$schedule->get_unrecognized_classes(),
			'The placeholder should record the unrecognized class name for operator review.'
		);

		// A bare top-level non-schedule is a structural rejection, not recoverable -> stays corrupt.
		$structural = serialize( new ActionScheduler_Test_Evil_Gadget() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->assertFalse( ActionScheduler_ScheduleDeserializer::unserialize( $structural ) );
	}
}
