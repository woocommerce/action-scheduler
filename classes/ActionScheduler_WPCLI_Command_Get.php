<?php

/**
 * Action Scheduler WP CLI command to get action info.
 */
class ActionScheduler_WPCLI_Command_Get extends ActionScheduler_Abstract_WPCLI_Command {

	/**
	 * @var string Format of timestamp.
	 */
	protected $timestamp_format = 'Y-m-d H:i:s T';

	/**
	 * @var array
	 */
	protected $query_args = array();

	/**
	 * @var ActionScheduler_Store
	 */
	protected $store = null;

	/**
	 * Construct.
	 */
	public function __construct( $args, $assoc_args ) {
		parent::__construct( $args, $assoc_args );

		$this->timestamp_format = \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'time-format', $this->timestamp_format );
	}

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
		$available_columns = array( 'id', 'hook', 'date', 'group', 'status', 'args' );
		$default_columns = array( 'id', 'hook', 'status', 'date' );
		$columns = $this->get_columns( $default_columns, $available_columns );

		$per_page = (int) \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'per_page', 5 );
		$offset = (int) \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'offset', 0 );

		if ( empty( $this->args[1] ) ) {
			$action_ids = $this->store->query_actions( array( 'per_page' => $per_page, 'offset' => $offset ) );
		} else {
			$action_ids = array_unique( explode( ',', $this->args[1] ) );
		}

		$progress_bar = \WP_CLI\Utils\make_progress_bar( 'Collecting data:', count( $action_ids ) * count( $columns ) );
		$rows = array();

		foreach ( $action_ids as $action_id ) {
			$action = $this->store->fetch_action( $action_id );
			$row = array();

			if ( is_a( $action, 'ActionScheduler_NullAction' ) ) {
				\WP_CLI::warning( 'Action with ID \'' . $action_id . '\' does not exist.' );
				foreach ( $columns as $column ) {
					$progress_bar->tick();
				}
				continue;
			}

			foreach ( $columns as $column ) {

				switch ( $column ) {

					case 'id':
						$row['id'] = $action_id;
						break;

					case 'hook':
						$row['hook'] = $action->get_hook();
						break;

					case 'date':
						$row['date'] = $this->store->get_date_gmt( $action_id )->format( $this->timestamp_format );
						break;

					case 'group':
						$row['group'] = $action->get_group();
						break;

					case 'status':
						$row['status'] = $this->store->get_status( $action_id );
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
		\WP_CLI\Utils\format_items( 'table', $rows, $columns );
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
	 * - latest: date of latest action
	 * - last-complete: date of last completed action
	 * - oldest-pending: date of earliest pending action
	 */
	protected function execute_hooks() {
		$available_columns = array( 'hook', 'overdue', 'future', 'total', 'oldest', 'latest', 'last-complete', 'oldest-pending' );
		$default_columns = array( 'hook', 'pending', 'complete' );
		$columns = $this->get_columns( $default_columns, $available_columns, true );

		if ( empty( $this->args[1] ) ) {
			$hooks = $this->store->action_hooks();
		} else {
			$hooks = array_unique( explode( ',', $this->args[1] ) );
		}

		$progress_bar = \WP_CLI\Utils\make_progress_bar( 'Collecting data:', count( $hooks ) * count( $columns ) );
		$rows = array();

		foreach ( $hooks as $hook ) {
			$query_args = array( 'hook' => $hook );
			$row = array();

			foreach ( $columns as $column ) {

				if ( in_array( $column, array_keys( $this->store->get_status_labels() ) ) ) {
					$status = $column;
					$column = '_status';
				}

				switch ( $column ) {

					case 'hook':
						$row['hook'] = $hook;
						break;

					case '_status':
						$row[ $status ] = $this->column__status( $status, $query_args );
						break;

					default:
						$callback = array( $this, 'column__' . str_replace( '-', '_', $column ) );
						if ( is_callable( $callback ) ) {
							$row[ $column ] = call_user_func_array( $callback, array( $query_args ) );
						}

				}

				$progress_bar->tick();
			}

			$rows[] = $row;
		}

		$progress_bar->finish();
		\WP_CLI\Utils\format_items( 'table', $rows, $columns );
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
	 * - latest: date of latest action
	 * - last-complete: date of last completed action
	 * - oldest-pending: date of earliest pending action
	 */
	protected function execute_groups() {
		$available_columns = array( 'group', 'overdue', 'future', 'total', 'oldest', 'latest', 'last-complete', 'oldest-pending' );
		$default_columns = array( 'group', 'total' );
		$columns = $this->get_columns( $default_columns, $available_columns, true );

		if ( empty( $this->args[1] ) ) {
			\WP_CLI::error( 'One or more group names are required.' );
		} else {
			$groups = explode( ',', $this->args[1] );
		}

		$progress_bar = \WP_CLI\Utils\make_progress_bar( 'Collecting data:', count( $groups ) * count( $columns ) );
		$rows = array();

		foreach ( $groups as $group ) {
			$query_args = array( 'group' => $group );
			$row = array();

			foreach ( $columns as $column ) {

				if ( in_array( $column, array_keys( $this->store->get_status_labels() ) ) ) {
					$status = $column;
					$column = '_status';
				}

				switch ( $column ) {

					case 'group':
						$row['group'] = $group;
						break;

					case '_status':
						$row[ $status ] = $this->column__status( $status, $query_args );
						break;

					default:
						$callback = array( $this, 'column__' . str_replace( '-', '_', $column ) );
						if ( is_callable( $callback ) ) {
							$row[ $column ] = call_user_func_array( $callback, array( $query_args ) );
						}

				}

				$progress_bar->tick();
			}

			$rows[] = $row;
		}

		$progress_bar->finish();

		\WP_CLI\Utils\format_items( 'table', $rows, $columns );
	}

	/**
	 * Prints data for specified or all action stati/status.
	 *
	 * Available columns:
	 * - status: action status
	 * - total: total count of actions with status
	 * - oldest: oldest date of action with status
	 * - latest: latest date of action with status
	 */
	protected function execute_stati() {
		$available_columns = array( 'status', 'total', 'latest', 'oldest' );
		$default_columns = array( 'status', 'total' );
		$columns = $this->get_columns( $default_columns, $available_columns );

		if ( empty( $this->args[1] ) ) {
			$stati = $this->store->get_status_labels();
		} else {
			$stati = explode( ',', $this->args[1] );
		}

		$progress_bar = \WP_CLI\Utils\make_progress_bar( 'Collecting data:', count( $stati ) * count( $columns ) );
		$rows = array();

		foreach ( $stati as $status => $label ) {
			$row = array();

			foreach ( $columns as $column ) {
				switch ( $column ) {

					case 'status':
						$row['status'] = $label;
						break;

					default:
						$callback = array( $this, 'column__' . str_replace( '-', '_', $column ) );
						if ( is_callable( $callback ) ) {
							$row[ $column ] = call_user_func_array( $callback, array( array( 'status' => $status ) ) );
						}

				}

				$progress_bar->tick();
			}

			$rows[] = $row;
		}

		$progress_bar->finish();
		\WP_CLI\Utils\format_items( 'table', $rows, $columns );
	}

	/**
	 * Column: status
	 *
	 * Display count of actions in status.
	 *
	 * @param string $status Action status.
	 * @param array $query_args Other query arguments.
	 * @return int
	 */
	protected function column__status( $status, $query_args ) {
		$query_args['status'] = $status;
		return (int) $this->store->query_actions( $query_args, 'count' );
	}

	/**
	 * Column: overdue
	 *
	 * Display count of pending actions that are overdue.
	 *
	 * @param array $query_args Other query arguments.
	 * @return int
	 */
	protected function column__overdue( $query_args ) {
		$query_args = array_merge( $query_args, array(
			'status' => $this->store::STATUS_PENDING,
			'date' => as_get_datetime_object()->format( 'Y-m-d H:i:s' ),
		) );

		return (int) $this->store->query_actions( $query_args, 'count' );
	}

	/**
	 * Column: future
	 *
	 * Display count of actions scheduled in future.
	 *
	 * @param array $query_args Other query arguments.
	 * @return int
	 */
	protected function column__future( $query_args ) {
		$query_args = array_merge( $query_args, array(
			'status' => $this->store::STATUS_PENDING,
			'date' => as_get_datetime_object()->format( 'Y-m-d H:i:s' ),
			'date_compare' => '>=',
		) );

		return (int) $this->store->query_actions( $query_args, 'count' );
	}

	/**
	 * Column: total
	 *
	 * Display total number of actions matching query arguments.
	 *
	 * @param array $query_args Other query arguments.
	 * @return int
	 */
	protected function column__total( $query_args ) {
		return (int) $this->store->query_actions( $query_args, 'count' );
	}

	/**
	 * Column: oldest
	 *
	 * Display oldest action scheduled date.
	 *
	 * @param array $query_args
	 * @return string
	 */
	protected function column__oldest( $query_args ) {
		error_log( print_r( $query_args, true ) );
		$query_args['per_page'] = 1;
		$action_ids = $this->store->query_actions( $query_args );

		if ( ! empty( $action_ids ) ) {
			return $this->store->get_date_gmt( $action_ids[0] )->format( $this->timestamp_format );
		}

		return '—';
	}

	/**
	 * Column: latest
	 *
	 * Display latest action scheduled date.
	 *
	 * @param array $query_args Other query args.
	 * @return string
	 */
	protected function column__latest( $query_args ) {
		$query_args = array_merge( $query_args, array(
			'per_page' => 1,
			'order' => 'DESC',
		) );

		$action_ids = $this->store->query_actions( $query_args );

		if ( !empty( $action_ids ) ) {
			return $this->store->get_date_gmt( $action_ids[0] )->format( $this->timestamp_format );
		}

		return '—';
	}

	/**
	 * Column: last-complete
	 *
	 * Display date of last complete action.
	 *
	 * @param array $query_args Other query args.
	 * @return string
	 */
	protected function column__last_complete( $query_args ) {
		$query_args = array_merge( $query_args, array(
			'per_page' => 1,
			'order' => 'DESC',
			'status' => $this->store::STATUS_COMPLETE,
		) );

		$action_ids = $this->store->query_actions( $query_args );

		if ( ! empty( $action_ids ) ) {
			return $this->store->get_date_gmt( $action_ids[0] )->format( $this->timestamp_format );
		}

		return '—';
	}

	/**
	 * Column: oldest-pending
	 *
	 * Display date of oldest, pending action.
	 *
	 * @param array $query_args Other query args.
	 * @return string
	 */
	protected function column__oldest_pending( $query_args ) {
		$query_args = array_merge( $query_args, array(
			'per_page' => 1,
			'status' => $this->store::STATUS_PENDING,
		) );

		$action_ids = $this->store->query_actions( $query_args );

		if ( ! empty( $action_ids ) ) {
			return $this->store->get_date_gmt( $action_ids[0] )->format( $this->timestamp_format );
		}

		return '—';
	}

}
