<?php

defined( 'ABSPATH' ) || exit;

/**
 * ActionScheduler_Mocker class.
 */
class ActionScheduler_Mocker {

	/**
	 * Do not run queues via async requests.
	 *
	 * @param null|ActionScheduler_Store $store Store instance.
	 */
	public static function get_queue_runner( ?ActionScheduler_Store $store = null ) {

		if ( ! $store ) {
			$store = ActionScheduler_Store::instance();
		}

		// Mark the recurring-actions check as already done so it doesn't queue a recurring action during unrelated tests.
		set_transient( 'as_is_ensure_recurring_actions_scheduled', true, HOUR_IN_SECONDS );

		return new ActionScheduler_QueueRunner( $store, null, null, self::get_async_request_queue_runner( $store ) );
	}

	/**
	 * Get an instance of the mock queue runner
	 *
	 * @param ActionScheduler_Store $store Store instance.
	 */
	protected static function get_async_request_queue_runner( ActionScheduler_Store $store ) {
		return new ActionScheduler_Mock_AsyncRequest_QueueRunner( $store );
	}
}
