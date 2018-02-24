<?php

/**
 * Class ActionScheduler_StoredAction
 *
 * An action which has been saved in the data store and is pending execution (i.e. not finished).
 */
class ActionScheduler_StoredAction extends ActionScheduler_AbstractAction implements ActionScheduler_Interface_Storable, ActionScheduler_Interface_Claimable {

	/** @var mixed */
	protected $id;

	/** @var string */
	protected $status;

	/** @var string */
	protected $claim_id = '';

	public function __construct( $id, $hook, $status, $claim_id = '', array $args = array(), ActionScheduler_Schedule $schedule = NULL, $group = '' ) {
		$schedule = empty( $schedule ) ? new ActionScheduler_NullSchedule() : $schedule;
		$this->set_id($id);
		$this->set_hook($hook);
		$this->set_status($status);
		$this->set_claim_id($claim_id);
		$this->set_args($args);
		$this->set_schedule($schedule);
		$this->set_group($group);
	}

	/**
	 * Sets the ID for this current action.
	 *
	 * @param mixed $id Action ID
	 *
	 * @return self
	 */
	protected function set_id( $id ) {
		$this->id = $id;
	}

	/**
	 * Returns the ID of this current action or throws a RuntimeException otherwise
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Sets the ID for this current action.
	 *
	 * @param mixed $id Action ID
	 *
	 * @return self
	 */
	protected function set_status( $status ) {
		$this->status = $status;
	}

	/**
	 * Returns the ID of this current action or throws a RuntimeException otherwise
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->status;
	}

	protected function set_claim_id( $claim_id ) {
		$this->claim_id = $claim_id;
	}

	/**
	 * Returns the claim ID. If this value is not set previous it will be read from the default store.
	 *
	 * @return string
	 */
	public function get_claim_id() {
		return $this->claim_id;
	}
}
