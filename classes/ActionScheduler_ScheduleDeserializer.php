<?php

/**
 * Safely turns a stored, serialized schedule blob back into a schedule object.
 *
 * Scheduled actions persist their schedule as a native PHP-serialized blob. Reading that blob
 * back with a bare unserialize() lets an attacker who can influence the stored bytes have PHP
 * instantiate arbitrary classes, running their __wakeup()/__destruct() magic methods (PHP object
 * injection). @see https://github.com/woocommerce/action-scheduler/issues/1318
 *
 * This class moves Action Scheduler's existing "is this really an ActionScheduler_Schedule?" check
 * to *before* any class is instantiated, using a two-phase deserialization:
 *
 *   1. Parse the blob with `allowed_classes => false`, which instantiates nothing — every object
 *      in the payload becomes a harmless __PHP_Incomplete_Class placeholder. No constructor,
 *      __wakeup(), __destruct() or autoloading runs.
 *   2. Inspect the class names actually present. Only if the outermost class implements
 *      ActionScheduler_Schedule, and every nested class is on a small vetted allow-list, do we
 *      re-run unserialize() a second time restricted to exactly that verified set of classes.
 *
 * The allow-list is derived, not hard-coded: any loaded schedule class (including third-party
 * ones) is trusted automatically because it implements ActionScheduler_Schedule, so extenders need
 * to do nothing. A blob referencing anything else is rejected the same way a corrupt blob already
 * is today, or — in shadow mode — allowed through untouched while a warning is surfaced.
 *
 * @since 3.10.0
 */
class ActionScheduler_ScheduleDeserializer {

	/**
	 * Maximum object-graph depth we will walk while vetting a blob.
	 *
	 * Real schedules nest only a handful of levels deep (a cron schedule's CronExpression graph is
	 * the deepest at ~6). Anything beyond this bound is treated as untrustworthy rather than walked.
	 *
	 * @var int
	 */
	const MAX_GRAPH_DEPTH = 100;

	/**
	 * Deserialize a stored schedule blob without instantiating unexpected classes.
	 *
	 * @param string $data The raw serialized schedule blob as stored in the database.
	 * @return ActionScheduler_Schedule|object|false The schedule object, or false if the blob is
	 *                                                unusable (corrupt, or rejected while enforcing).
	 *                                                Callers already treat false like a corrupt blob.
	 */
	public static function unserialize( $data ) {
		if ( ! is_string( $data ) || '' === $data ) {
			return false;
		}

		// Phase 1: parse the blob without instantiating any class from it. This is inert: objects
		// become __PHP_Incomplete_Class placeholders, so no __wakeup()/__destruct()/autoload runs.
		$inert = @unserialize( $data, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged

		// A schedule is always an object graph. Anything else is corrupt/unexpected data.
		if ( ! is_object( $inert ) ) {
			return false;
		}

		$top_class      = self::class_name_of( $inert );
		$nested_classes = array();
		$seen           = array();

		// A tampered blob can encode a self-referential or pathologically deep object graph. If we
		// cannot fully walk it within a sane bound, we refuse to vouch for it and reject.
		if ( ! self::collect_class_names( $inert, $nested_classes, true, $seen, 0 ) ) {
			return self::handle_rejection( $data, $top_class, $top_class, $nested_classes );
		}

		// Phase 2a: the outermost object must be a real, loaded schedule class. This is the same
		// contract ActionScheduler_Store::validate_schedule() already enforces, only applied before
		// instantiation instead of after.
		if ( ! self::is_allowed_top_level_class( $top_class ) ) {
			return self::handle_rejection( $data, $top_class, $top_class, $nested_classes );
		}

		// Phase 2b: every nested object must be on the vetted allow-list.
		foreach ( $nested_classes as $nested_class ) {
			if ( ! self::is_allowed_nested_class( $nested_class ) ) {
				return self::handle_rejection( $data, $nested_class, $top_class, $nested_classes );
			}
		}

		// All classes vetted: re-hydrate for real, restricting instantiation to exactly that set.
		$allowed = array_values( array_unique( array_merge( array( $top_class ), $nested_classes ) ) );

		// Silenced to match the stores' historical @unserialize() behaviour: a blob that parsed inert
		// in phase 1 will parse here too, but we keep the same log-quiet contract regardless.
		return @unserialize( $data, array( 'allowed_classes' => $allowed ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Decide whether the outermost class in a schedule blob may be instantiated.
	 *
	 * Allowed iff the class is loaded and implements ActionScheduler_Schedule. A class that is not
	 * loaded cannot be validated (and, prior to this change, already produced a broken action), so
	 * rejecting it here does not regress any case that previously worked.
	 *
	 * @param string $class_name Class name discovered in the blob.
	 * @return bool
	 */
	protected static function is_allowed_top_level_class( $class_name ) {
		if ( ! is_string( $class_name ) || '' === $class_name || ! class_exists( $class_name ) ) {
			return false;
		}

		$implements = class_implements( $class_name );

		return is_array( $implements ) && isset( $implements['ActionScheduler_Schedule'] );
	}

	/**
	 * Decide whether a nested class (a property of the schedule) may be instantiated.
	 *
	 * The only classes Action Scheduler itself ever nests inside a schedule are the bundled
	 * CronExpression family. Composite schedules that nest another schedule are also fine. The
	 * default list is filterable so extenders can vet their own supporting classes.
	 *
	 * @param string $class_name Class name discovered nested in the blob.
	 * @return bool
	 */
	protected static function is_allowed_nested_class( $class_name ) {
		if ( ! is_string( $class_name ) || '' === $class_name ) {
			return false;
		}

		if ( in_array( $class_name, self::get_allowed_nested_classes(), true ) ) {
			return true;
		}

		// A schedule nested inside another schedule (composite schedules) is safe by the same rule
		// as the top-level check.
		if ( class_exists( $class_name ) ) {
			$implements = class_implements( $class_name );
			if ( is_array( $implements ) && isset( $implements['ActionScheduler_Schedule'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The supporting classes Action Scheduler is willing to instantiate when nested in a schedule.
	 *
	 * @return string[]
	 */
	public static function get_allowed_nested_classes() {
		$default = array(
			'CronExpression',
			'CronExpression_FieldFactory',
			'CronExpression_AbstractField',
			'CronExpression_MinutesField',
			'CronExpression_HoursField',
			'CronExpression_DayOfMonthField',
			'CronExpression_MonthField',
			'CronExpression_DayOfWeekField',
			'CronExpression_YearField',
		);

		/**
		 * Filters the list of non-schedule classes that may be instantiated when nested inside a
		 * stored schedule blob during deserialization.
		 *
		 * Add the fully-qualified names of any supporting classes your custom schedule stores as
		 * properties. Schedule classes themselves (implementing ActionScheduler_Schedule) are
		 * always allowed and do not need to be listed here.
		 *
		 * @since 3.10.0
		 *
		 * @param string[] $default List of allowed nested class names.
		 */
		return (array) apply_filters( 'action_scheduler_allowed_nested_schedule_classes', $default );
	}

	/**
	 * Whether an unexpected class causes the blob to be rejected (true) or merely reported while the
	 * legacy, unrestricted deserialization proceeds (false, "shadow mode").
	 *
	 * Enforcement is on by default. Site owners can opt into shadow mode for a release to gather
	 * data before enforcing, without changing any stored data.
	 *
	 * @return bool
	 */
	public static function is_enforced() {
		/**
		 * Filters whether Action Scheduler rejects schedule blobs referencing unexpected classes.
		 *
		 * Return false to run in "shadow mode": unexpected classes are reported via the
		 * `action_scheduler_unexpected_schedule_class` action but the blob is still deserialized
		 * exactly as it was before this hardening. Useful to confirm the allow-list is complete for
		 * your site before switching enforcement on.
		 *
		 * @since 3.10.0
		 *
		 * @param bool $enforced Whether to reject blobs with unexpected classes. Default true.
		 */
		return (bool) apply_filters( 'action_scheduler_enforce_schedule_allowed_classes', true );
	}

	/**
	 * Handle a blob that references a class outside the vetted set.
	 *
	 * Always surfaces the event for observability. Under enforcement (the default) the blob is
	 * rejected by returning false, which callers already handle like a corrupt schedule. In shadow
	 * mode the pre-hardening behaviour is preserved so a mis-tuned allow-list cannot disrupt a site.
	 *
	 * @param string   $data            The raw serialized blob.
	 * @param string   $offending_class The first class that failed validation.
	 * @param string   $top_class       The outermost class in the blob.
	 * @param string[] $nested_classes  All nested class names discovered in the blob.
	 * @return object|false
	 */
	protected static function handle_rejection( $data, $offending_class, $top_class, array $nested_classes ) {
		// Resolve enforcement once so the reported value and the branch taken cannot disagree, and the
		// action_scheduler_enforce_schedule_allowed_classes filter runs a single time.
		$enforced = self::is_enforced();

		/**
		 * Fires when a stored schedule blob references a class Action Scheduler did not expect.
		 *
		 * @since 3.10.0
		 *
		 * @param string   $offending_class The first disallowed class encountered.
		 * @param string   $top_class       The outermost class in the blob.
		 * @param string[] $nested_classes  All nested class names discovered in the blob.
		 * @param bool     $enforced        Whether the blob was rejected (true) or allowed through
		 *                                  in shadow mode (false).
		 */
		do_action( 'action_scheduler_unexpected_schedule_class', $offending_class, $top_class, $nested_classes, $enforced );

		if ( $enforced ) {
			return false;
		}

		// Shadow mode: behave exactly as Action Scheduler did before this change.
		return @unserialize( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Get the class name of an object parsed in "inert" mode.
	 *
	 * Objects whose class was disallowed become __PHP_Incomplete_Class, which hides the original
	 * name behind get_class(); it lives in the __PHP_Incomplete_Class_Name pseudo-property instead.
	 * stdClass is never converted by PHP, so its real name is returned directly.
	 *
	 * @param object $maybe_incomplete Object parsed with allowed_classes => false.
	 * @return string The original class name, or '' if it cannot be determined.
	 */
	protected static function class_name_of( $maybe_incomplete ) {
		if ( $maybe_incomplete instanceof __PHP_Incomplete_Class ) {
			$vars = (array) $maybe_incomplete;
			return isset( $vars['__PHP_Incomplete_Class_Name'] ) ? (string) $vars['__PHP_Incomplete_Class_Name'] : '';
		}

		return get_class( $maybe_incomplete );
	}

	/**
	 * Recursively collect the class names of every object nested inside a value.
	 *
	 * A tampered blob can decode (via `r:`/`R:` references) into a self-referential or extremely deep
	 * object graph. Walking that naively would recurse until the stack overflows, so we track objects
	 * already visited (to short-circuit cycles and shared references) and cap the recursion depth.
	 *
	 * @param mixed    $value   The value to walk (object, array, or scalar).
	 * @param string[] $found   Accumulator of discovered nested class names (by reference).
	 * @param bool     $is_root Whether $value is the outermost object (excluded from $found).
	 * @param array    $seen    Object ids already visited, keyed by spl_object_id (by reference).
	 * @param int      $depth   Current recursion depth.
	 * @return bool True if the value was fully walked; false if it was too deep to vet safely.
	 */
	protected static function collect_class_names( $value, array &$found, $is_root, array &$seen, $depth ) {
		if ( $depth > self::MAX_GRAPH_DEPTH ) {
			return false;
		}

		if ( is_object( $value ) ) {
			$object_id = spl_object_id( $value );
			if ( isset( $seen[ $object_id ] ) ) {
				return true; // Already walked (cycle or shared reference); nothing new to collect.
			}
			$seen[ $object_id ] = true;

			if ( ! $is_root ) {
				$found[] = self::class_name_of( $value );
			}

			foreach ( (array) $value as $key => $child ) {
				if ( '__PHP_Incomplete_Class_Name' === $key ) {
					continue;
				}
				if ( ! self::collect_class_names( $child, $found, false, $seen, $depth + 1 ) ) {
					return false;
				}
			}

			return true;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $child ) {
				if ( ! self::collect_class_names( $child, $found, false, $seen, $depth + 1 ) ) {
					return false;
				}
			}
		}

		return true;
	}
}
