<?php

/**
 * Class ActionScheduler_RecurringActionScheduler
 *
 * This class ensures that the `action_scheduler_schedule_recurring_actions` hook is triggered on a daily interval. This
 * simplifies the process for other plugins to register their recurring actions without requiring each plugin to query
 * or schedule actions independently on every request.
 */
class ActionScheduler_RecurringActionScheduler {

	/**
	 * Initialize the instance.  Should only be run on a single instance per request.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
			add_action( 'action_scheduler_init', [ __CLASS__, 'schedule_recurring_scheduler_hook' ] );
		}
	}

	/**
	 * Schedule the recurring `action_scheduler_schedule_recurring_actions` action if not already scheduled.
	 *
	 * @return void
	 */
	public function schedule_recurring_scheduler_hook(): void {
		if ( false === wp_cache_get( 'as_is_recurring_scheduler_scheduled' ) ) {
			if ( ! as_has_scheduled_action( 'action_scheduler_schedule_recurring_actions' ) ) {
				as_schedule_recurring_action(
					time(),
					DAY_IN_SECONDS, // Hourly interval
					'action_scheduler_schedule_recurring_actions',
					[],
					'ActionScheduler',
					true,
					20
				);
			}
			wp_cache_set( 'as_is_recurring_scheduler_scheduled', true, HOUR_IN_SECONDS );
		}
	}
}
