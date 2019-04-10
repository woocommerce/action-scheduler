<?php

class ActionScheduler_WPCLI_Command_Action extends ActionScheduler_Abstract_WPCLI_Command {

	/**
	 * @var ActionScheduler_Store
	 */
	protected $store = null;

	/**
	 * Identify and run subcommand.
	 */
	public function execute() {
		$this->store = ActionScheduler::store();
		$callback = array( $this, $this->args[0] );

		if ( !is_callable( $callback ) ) {
			$this->error( 'No ' . $this->args[0] . ' subcommand.' );
		}

		call_user_func( $callback );
	}

	/**
	 * Creates an action.
	 */
	public function create() {

	}

	/**
	 * Deletes an existing action.
	 */
	public function delete() {

	}

	/**
	 * Duplicates an existing action.
	 */
	public function duplicate() {

	}

	/**
	 * Vertifies whether an action exists.
	 */
	public function exists() {

	}

	/**
	 * Generates some actions.
	 */
	public function generate() {

	}

	/**
	 * Get details about an action.
	 */
	public function get() {
		$action_id = $this->args[1];
		$action = $this->store->fetch_action( $action_id );

		if ( empty( $action ) || is_a( $action, 'ActionScheduler_NullAction' ) ) {
			$this->error( $action_id . ' is not an action.' );
		}

		$fields = array(
			'id'     => $action_id,
			'hook'   => $action->get_hook(),
			'args'   => $action->get_args(),
			'status' => $this->store->get_status( $action_id ),
			'date'   => $action->get_schedule()->next()->format( 'Y-m-d H:i:s T' ),
			'group'  => $action->get_group(),
		);

		$rows = array();

		foreach ( $fields as $field => $value ) {
			$rows[] = array(
				'field' => $field,
				'value' => $value,
			);
		}

		$this->table( $rows, $this->get_columns( array( 'field', 'value' ) ) );
	}

	/**
	 * Gets a list of actions.
	 */
	public function list() {

	}

	/**
	 * Runs the action.
	 */
	public function run() {

	}

	/**
	 * Updates one or more existing actions.
	 */
	public function update() {

	}

}
