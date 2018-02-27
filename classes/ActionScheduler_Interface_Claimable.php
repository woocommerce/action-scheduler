<?php
/**
 * Interface ActionScheduler_Interface_Claimable
 *
 * Define the public methods provided by claimable objects, like an action
 *
 * @since 1.6.0
 */
interface ActionScheduler_Interface_Claimable {

	/**
	 * Get the claim ID of the action.
	 *
	 * @author Jeremy Pry
	 * @return mixed
	 */
	public function get_claim_id();
}