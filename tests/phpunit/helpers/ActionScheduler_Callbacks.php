<?php

class ActionScheduler_Callbacks {
	/**
	 * Scheduled action hook that can be used when we want to simulate an action
	 * with a registered callback.
	 */
	const HOOK_WITH_CALLBACK = 'hook_with_callback';

	/**
	 * Setup callbacks for different types of hook.
	 */
	public static function add_callbacks() {
		add_action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array( __CLASS__, 'empty_callback') );
	}

	/**
	 * Remove callbacks.
	 */
	public static function remove_callbacks() {
		remove_action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array( __CLASS__, 'empty_callback' ) );
	}

	/**
	 * This stub is used as the callback function for the ActionScheduler_Callbacks::HOOK_WITH_CALLBACK hook.
	 *
	 * Action Scheduler will mark actions as 'failed' if a callback does not exist, this
	 * simply serves to act as the callback for various test scenarios in child classes.
	 */
	public static function empty_callback() {}
}
