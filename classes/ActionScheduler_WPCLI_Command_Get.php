<?php

/**
 * Action Scheduler WP CLI command to get action info.
 */
class ActionScheduler_WPCLI_Command_Get extends ActionScheduler_Abstract_WPCLI_Command {

	/**
	 * @var ActionScheduler_Store
	 */
	protected $store = null;

	/**
	 * Execute command.
	 */
	public function execute() {
		$this->store = ActionScheduler::store();
		$stati = array_keys( $this->store->get_status_labels() );

		if ( in_array( $this->args[0], $stati ) ) {
			$this->args[1] = $this->args[0];
			$this->args[0] = 'status';
		}

		switch ( $this->args[0] ) {

			case 'id':
			case 'ids':
			case 'action':
			case 'actions':
				$this->execute_ids();
				break;

			case 'hook':
			case 'hooks':
			case 'title':
			case 'titles':
				$this->execute_hooks();
				break;

			case 'group':
			case 'groups':
				$this->execute_groups();
				break;

			case 'stati':
			case 'status':
			case 'statuses':
				$this->execute_stati();
				break;

			default:
				$this->execute_ids();

		}
	}

	/**
	 * Print data for specified action IDs.
	 *
	 * Available columns: id, hook, date, group, status, args
	 */
	protected function execute_ids() {
		$columns = $this->get_columns( array( 'id', 'hook', 'status', 'date' ) );
		$per_page = (int) \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'per_page', 5 );
		$offset = (int) \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'offset', 0 );
		$rows = array();

		if ( empty( $this->args[1] ) ) {
			$ids = $this->store->query_actions( array( 'per_page' => $per_page, 'offset' => $offset ) );
		} else {
			$ids = explode( ',', $this->args[1] );
		}

		$progress_bar = \WP_CLI\Utils\make_progress_bar( 'Collecting data:', count( $ids ) * count( $columns ) );

		foreach ( $ids as $id ) {
			$action = $this->store->fetch_action( $id );
			$row = array();

			foreach ( $columns as $column ) {

				switch ( $column ) {

					case 'id':
						$row[ $column ] = $id;
						break;

					case 'hook':
						$row['hook'] = $action->get_hook();
						break;

					case 'date':
						$row['date'] = $this->store->get_date_gmt( $id )->format( 'Y-m-d H:i:s T' );
						break;

					case 'group':
						$row['group'] = $action->get_group();
						break;

					case 'status':
						$row['status'] = $this->store->get_status( $id );
						break;

					case 'args':
						$row['args'] = $action->get_args();
						break;

				}

				$progress_bar->tick();
			}

			$rows[] = $row;
		}

		$progress_bar->finish();
		$this->table( $rows, $columns );
	}

	/**
	 * Prints data for specified or all hooks.
	 *
	 * Available columns:
	 * - hook: hook name
	 * - {status}: count of any post statuses
	 * - overdue: count of pending actions that are in the past
	 * - future: count of scheduled actions
	 * - total: total count of actions
	 * - oldest: date of oldest action
	 * - newest: date of newest action
	 * - last-complete: date of last completed action
	 * - oldest-pending: date of earliest pending action
	 */
	protected function execute_hooks() {
		$columns = $this->get_columns( array( 'hook', 'pending', 'complete' ) );
		$rows = array();

		if ( empty( $this->args[1] ) ) {
			$hooks = $this->store->action_hooks();
		} else {
			$hooks = explode( ',', $this->args[1] );
		}

		$progress_bar = \WP_CLI\Utils\make_progress_bar( 'Collecting data:', count( $hooks ) * count( $columns ) );

		foreach ( $hooks as $hook ) {
			$row = array();

			foreach ( $columns as $column ) {

				if ( in_array( $column, array_keys( $this->store->get_status_labels() ) ) ) {
					$status = $column;
					$column = 'status';
				}

				switch ( $column ) {

					case 'hook':
						$row['hook'] = $hook;
						break;

					case 'status':
						$row[ $status ] = $this->store->query_actions( array( 'hook' => $hook, 'status' => $status ), 'count' );
						unset( $status );
						break;

					case 'overdue':
						$row['overdue'] = $this->store->query_actions( array( 'hook' => $hook, 'status' => $this->store::STATUS_PENDING, 'date' => as_get_datetime_object()->format( 'Y-m-d H:i:s' ) ), 'count' );
						break;

					case 'future':
						$row['future'] = $this->store->query_actions( array( 'hook' => $hook, 'status' => $this->store::STATUS_PENDING, 'date' => as_get_datetime_object()->format( 'Y-m-d H:i:s' ), 'date_compare' => '>=' ), 'count' );
						break;

					case 'total':
						$row['total'] = $this->store->query_actions( array( 'hook' => $hook ), 'count' );
						break;

					case 'oldest':
						$action_ids = $this->store->query_actions( array( 'hook' => $hook, 'per_page' => 1 ) );
						if ( empty( $action_ids ) ) {
							$row['oldest'] = '—';
						} else {
							$row['oldest'] = $this->store->get_date_gmt( $action_ids[0] )->format( 'Y-m-d H:i:s T' );
						}
						break;

					case 'newest':
						$action_ids = $this->store->query_actions( array( 'hook' => $hook, 'per_page' => 1, 'order' => 'DESC' ) );
						if ( empty( $action_ids ) ) {
							$row['newest'] = '—';
						} else {
							$row['newest'] = $this->store->get_date_gmt( $action_ids[0] )->format( 'Y-m-d H:i:s T' );
						}
						break;

					case 'last-complete':
						$action_ids = $this->store->query_actions( array( 'hook' => $hook, 'per_page' => 1, 'order' => 'DESC', 'status' => $this->store::STATUS_COMPLETE ) );
						if ( empty( $action_ids ) ) {
							$row['last-complete'] = '—';
						} else {
							$row['last-complete'] = $this->store->get_date_gmt( $action_ids[0] )->format( 'Y-m-d H:i:s T' );
						}
						break;

					case 'oldest-pending':
						$action_ids = $this->store->query_actions( array( 'hook' => $hook, 'per_page' => 1, 'status' => $this->store::STATUS_PENDING ) );
						if ( empty( $action_ids ) ) {
							$row['oldest-pending'] = '—';
						} else {
							$row['oldest-pending'] = $this->store->get_date_gmt( $action_ids[0] )->format( 'Y-m-d H:i:s T' );
						}
						break;

				}

				$progress_bar->tick();
			}

			$rows[] = $row;
		}

		$progress_bar->finish();
		$this->table( $rows, $columns );
	}

	/**
	 * Prints data for specified or all groups.
	 *
	 * Available columns:
	 * - group: group name
	 * - {status}: count of any post statuses
	 * - overdue: count of pending actions that are in the past
	 * - future: count of scheduled actions
	 * - total: total count of actions
	 * - oldest: date of oldest action
	 * - newest: date of newest action
	 * - last-complete: date of last completed action
	 * - oldest-pending: date of earliest pending action
	 */
	protected function execute_groups() {
		$columns = $this->get_columns( array( 'group', 'total' ) );
		$rows = array();

		if ( empty( $this->args[1] ) ) {
			$this->error( 'One or more group names are required.' );
		} else {
			$groups = explode( ',', $this->args[1] );
		}

		$progress_bar = \WP_CLI\Utils\make_progress_bar( 'Collecting data:', count( $groups ) * count( $columns ) );

		foreach ( $groups as $group ) {
			$row = array();

			foreach ( $columns as $column ) {

				if ( in_array( $column, array_keys( $this->store->get_status_labels() ) ) ) {
					$status = $column;
					$column = 'status';
				}

				switch ( $column ) {

					case 'group':
						$row['group'] = $group;
						break;

					case 'status':
						$row[ $status ] = $this->store->query_actions( array( 'group' => $group, 'status' => $status ), 'count' );
						unset( $status );
						break;

					case 'overdue':
						$row['overdue'] = $this->store->query_actions( array( 'group' => $group, 'status' => $this->store::STATUS_PENDING, 'date' => as_get_datetime_object()->format( 'Y-m-d H:i:s' ) ), 'count' );
						break;

					case 'future':
						$row['future'] = $this->store->query_actions( array( 'group' => $group, 'status' => $this->store::STATUS_PENDING, 'date' => as_get_datetime_object()->format( 'Y-m-d H:i:s' ), 'date_compare' => '>=' ), 'count' );
						break;

					case 'total':
						$row['total'] = $this->store->query_actions( array( 'group' => $group ), 'count' );
						break;

					case 'oldest':
						$action_ids = $this->store->query_actions( array( 'group' => $group, 'per_page' => 1 ) );
						if ( empty( $action_ids ) ) {
							$row['oldest'] = '—';
						} else {
							$row['oldest'] = $this->store->get_date_gmt( $action_ids[0] )->format( 'Y-m-d H:i:s T' );
						}
						break;

					case 'newest':
						$action_ids = $this->store->query_actions( array( 'group' => $group, 'per_page' => 1, 'order' => 'DESC' ) );
						if ( empty( $action_ids ) ) {
							$row['newest'] = '—';
						} else {
							$row['newest'] = $this->store->get_date_gmt( $action_ids[0] )->format( 'Y-m-d H:i:s T' );
						}
						break;

					case 'last-complete':
						$action_ids = $this->store->query_actions( array( 'group' => $group, 'per_page' => 1, 'order' => 'DESC', 'status' => $this->store::STATUS_COMPLETE ) );
						if ( empty( $action_ids ) ) {
							$row['last-complete'] = '—';
						} else {
							$row['last-complete'] = $this->store->get_date_gmt( $action_ids[0] )->format( 'Y-m-d H:i:s T' );
						}
						break;

					case 'oldest-pending':
						$action_ids = $this->store->query_actions( array( 'group' => $group, 'per_page' => 1, 'status' => $this->store::STATUS_PENDING ) );
						if ( empty( $action_ids ) ) {
							$row['oldest-pending'] = '—';
						} else {
							$row['oldest-pending'] = $this->store->get_date_gmt( $action_ids[0] )->format( 'Y-m-d H:i:s T' );
						}
						break;

				}

				$progress_bar->tick();
			}

			$rows[] = $row;
		}

		$progress_bar->finish();
		$this->table( $rows, $columns );
	}

	/**
	 * Prints data for specified or all action stati/status.
	 *
	 * Available columns:
	 * - status: action status
	 * - total: total count of actions with status
	 * - oldest: oldest date of action with status
	 * - newest: newest date of action with status
	 */
	protected function execute_stati() {
		$columns = $this->get_columns( array( 'status', 'total' ) );
		$rows = array();

		if ( empty( $this->args[1] ) ) {
			$stati = $this->store->get_status_labels();
		} else {
			$stati = explode( ',', $this->args[1] );
		}

		$progress_bar = \WP_CLI\Utils\make_progress_bar( 'Collecting data:', count( $stati ) * count( $columns ) );

		foreach ( $stati as $status => $label ) {
			$row = array();

			foreach ( $columns as $column ) {
				switch ( $column ) {

					case 'status':
						$row['status'] = $label;
						break;

					case 'total':
						$row['total'] = $this->store->query_actions( array( 'status' => $status ), 'count' );
						break;

					case 'oldest':
						$action_ids = $this->store->query_actions( array( 'status' => $status, 'per_page' => 1 ) );
						if ( empty( $action_ids ) ) {
							$row['oldest'] = '—';
						} else {
							$row['oldest'] = $this->store->get_date_gmt( $action_ids[0] )->format( 'Y-m-d H:i:s T' );
						}
						break;

					case 'newest':
						$action_ids = $this->store->query_actions( array( 'status' => $status, 'per_page' => 1, 'order' => 'DESC' ) );
						if ( empty( $action_ids ) ) {
							$row['newest'] = '—';
						} else {
							$row['newest'] = $this->store->get_date_gmt( $action_ids[0] )->format( 'Y-m-d H:i:s T' );
						}
						break;

				}

				$progress_bar->tick();
			}

			$rows[] = $row;
		}

		$progress_bar->finish();
		$this->table( $rows, $columns );
	}

}
