<?php
/**
 * Interface ActionScheduler_Interface_Action
 *
 * Define public methods for all action types to provide
 *
 * @since 1.6.0
 */
interface ActionScheduler_Interface_Action {

	/**
	 * Get the hook for the Action.
	 *
	 * @author Jeremy Pry
	 * @return string
	 */
	public function get_hook();

	/**
	 * Get the arguments used for the Action.
	 *
	 * @author Jeremy Pry
	 * @return array
	 */
	public function get_args();

	/**
	 * Get the arguments used for the Action.
	 *
	 * @author Jeremy Pry
	 * @return array
	 */
	public function get_group();
}