<?php
/**
 * Class ActionScheduler_AbstractAction
 *
 * Define shared methods and data for all action types.
 *
 * @since 1.6.0
 */
abstract class ActionScheduler_AbstractAction implements ActionScheduler_Interface_Action, ActionScheduler_Interface_Scheduled {

	/** @var string */
	protected $hook = '';

	/** @var array */
	protected $args = array();

	/** @var ActionScheduler_Schedule */
	protected $schedule = NULL;

	/** @var string */
	protected $group = '';

	/** @var bool */
	protected $is_finished = false;

	public function execute() {
		do_action_ref_array( $this->get_hook(), $this->get_args() );
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
		return $this->is_finished;
	}
}
