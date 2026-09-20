<?php

/**
 * Store test double.
 */
class ActionScheduler_WPCLI_QueueRunner_Test_Store {

	/**
	 * Whether release_claim() was called.
	 *
	 * @var bool
	 */
	public $claim_released = false;

	/**
	 * Return the expected claim ID.
	 *
	 * @param int $action_id Action ID.
	 * @return int
	 */
	public function get_claim_id( $action_id ) {
		unset( $action_id );
		return 1;
	}

	/**
	 * Record claim release.
	 *
	 * @param object $claim Claim.
	 */
	public function release_claim( $claim ) {
		unset( $claim );
		$this->claim_released = true;
	}
}
