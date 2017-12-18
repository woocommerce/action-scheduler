<?php

/**
 * Class ActionScheduler_Action
 */
class ActionScheduler_Action {
	protected $hook = '';
	protected $args = array();
	/** @var ActionScheduler_Schedule */
	protected $schedule = NULL;
	protected $group = '';

	/** @var mixed */
	protected $id;

	/** @var int */
	protected $status;

	/** @var mixed */
	protected $claim_id;

	/**
	 * This array keeps track of all the properties that were set through the setters.
	 *
	 * Why is it needed?
	 *
	 *  1. If a variable was not assigned it may be populated on demand.
	 *  2. FALSE, NULL or 0 could be valid properties value, thus making it impossible to tell
	 *     without an external aid if our properties were assigned or not.
	 *
	 * To make things efficent the value of this array is always TRUE and the key is the property
	 * name.
	 *
	 * @type array
	 */
	protected $has = array();

	public function __construct( $hook, array $args = array(), ActionScheduler_Schedule $schedule = NULL, $group = '' ) {
		$schedule = empty( $schedule ) ? new ActionScheduler_NullSchedule() : $schedule;
		$this->set_hook($hook);
		$this->set_schedule($schedule);
		$this->set_args($args);
		$this->set_group($group);
	}

	/**
	 * Sets the ID for this current action.
	 *
	 * @param mixed $id Action ID
	 *
	 * @return self
	 */
	public function set_id( $id ) {
		$this->has['id'] = true;
		$this->id = $id;
		return $this;
	}

	/**
	 * Returns the ID of this current action or throws a RuntimeException otherwise
	 *
	 * @return mixed
	 */
	public function get_id() {
		if ( empty( $this->has['id'] ) ) {
			throw new RuntimeException( 'Cannot get ID from Action object ' );
		}
		return $this->id;
	}

	public function set_claim_id( $claim_id ) {
		$this->has['claim_id'] = true;
		$this->claim_id = $claim_id;
		return $this;
	}

	/**
	 * Returns the claim ID. If this value is not set previous it will be read from the default store.
	 *
	 * @return mixed
	 */
	public function get_claim_id() {
		if ( empty( $this->has['claim_id'] ) ) {
			$this->set_claim_id( ActionScheduler::store()->get_claim_id_for_action( $this->get_id() ) );
		}
		return $this->claim_id;
	}

	public function set_status( $status ) {
		$this->has['status'] = true;
		$this->status = $status;
		return $this;
	}


	/**
	 * Returns the Action status. If this value is not set previous it will be read from the default store.
	 *
	 * @return mixed
	 */
	public function get_status() {
		if ( empty( $this->has['status'] ) ) {
			$this->set_status( ActionScheduler::store()->get_status( $this->get_id() ) );
		}

		return $this->status;
	}

	public function execute() {
		return do_action_ref_array($this->get_hook(), $this->get_args());
	}

	/**
	 * @param string $hook
	 * @return void
	 */
	protected function set_hook( $hook ) {
		$this->hook = $hook;
	}

	public function get_hook() {
		return $this->hook;
	}

	protected function set_schedule( ActionScheduler_Schedule $schedule ) {
		$this->schedule = $schedule;
	}

	/**
	 * @return ActionScheduler_Schedule
	 */
	public function get_schedule() {
		return $this->schedule;
	}

	protected function set_args( array $args ) {
		$this->args = $args;
	}

	public function get_args() {
		return $this->args;
	}

	/**
	 * @param string $group
	 */
	protected function set_group( $group ) {
		$this->group = $group;
	}

	/**
	 * @return string
	 */
	public function get_group() {
		return $this->group;
	}

	/**
	 * @return bool If the action has been finished
	 */
	public function is_finished() {
		return FALSE;
	}
}
 
