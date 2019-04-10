<?php

class ActionScheduler_WPCLI_Command_Action {

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
	 * Verifies whether an action exists.
	 *
	 * ## OPTIONS
	 *
	 * <action_id>
	 * : ID of the action to check if exists.
	 */
	public function exists( $args, $assoc_args ) {
		$store = ActionScheduler::store();

		$action_id = absint( $args[0] );
		$action = $store->fetch_action( $action_id );

		if ( !empty( $action ) && !is_a( $action, 'ActionScheduler_NullAction' ) ) {
			\WP_CLI::success( 'Action with ID ' . $action_id . ' exists.' );
		}
	}

	/**
	 * Generates some actions.
	 */
	public function generate() {

	}

	/**
	 * Get details about an action.
	 *
	 * ## OPTIONS
	 *
	 * <action_id>
	 * : ID of action to get details about.
	 */
	public function get( $args, $assoc_args ) {
		$store = ActionScheduler::store();
		$action_id = absint( $args[0] );
		$action = $store->fetch_action( $action_id );

		if ( empty( $action ) || is_a( $action, 'ActionScheduler_NullAction' ) ) {
			\WP_CLI::error( $action_id . ' is not an action.' );
		}

		$fields = array(
			'id'     => $action_id,
			'hook'   => $action->get_hook(),
			'args'   => $action->get_args(),
			'status' => $store->get_status( $action_id ),
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

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'field', 'value' ) );
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
