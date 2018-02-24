<?php
/**
 * Interface ActionScheduler_Interface_Storable
 *
 * Define public methods for all action types to provide
 *
 * @since 1.6.0
 */
interface ActionScheduler_Interface_Storable {

	/**
	 * Get the ID of the action.
	 *
	 * @author Jeremy Pry
	 * @return int
	 */
	public function get_id();

	/**
	 * Get the status of the action.
	 *
	 * @author Jeremy Pry
	 * @return string
	 */
	public function get_status();
}