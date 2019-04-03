<?php

class ActionScheduler_WPCLI_Command_Info extends ActionScheduler_Abstract_WPCLI_Command {

	/**
	 * Execute command.
	 */
	public function execute() {
		$hooks = \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'hooks', false );
		$past_due = \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'past-due', false );

		if ( $past_due ) {
			$this->past_due( $hooks );
		} else if ( $hooks ) {
			$this->hooks( $hooks );
		} else {
			$this->default();
		}
	}

	/**
	 * Print info on past due actions.
	 *
	 * @param string $hooks
	 */
	protected function past_due( $hooks ) {
		$store = ActionScheduler::store();
		$columns = explode( ',', \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'columns', 'hook,total' ) );

		if ( true === $hooks ) {
			$hooks = $store->action_hooks();
		} else if ( !empty( $hooks ) ) {
			$hooks = explode( ',', $hooks );
		}

		$args = array(
			'status' => 'pending',
			'date' => as_get_datetime_object()->format( 'Y-m-d H:i:s' ),
			'date_compare' => '<=',
		);

		if ( !empty( $hooks ) ) {
			$rows = array();

			foreach ( $hooks as $hook ) {
				$args['hook'] = $hook;

				$rows[] = array(
					'hook' => $hook,
					'total' => $store->query_actions( $args, 'count' ),
				);
			}

			$this->table( $rows, $columns );
		}

		unset( $args['hook'] );
		$this->log( 'Total past due: ' . $store->query_actions( $args, 'count' ) );
	}

	/**
	 * Print counts for hooks.
	 *
	 * @param string $hooks
	 */
	protected function hooks( $hooks ) {
		$store = ActionScheduler::store();
		$stati = array_keys( $store->get_status_labels() );
		$columns = explode( ',', \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'columns', 'hook,total' ) );

		$rows = array();

		if ( true === $hooks ) {
			$hooks = $store->action_hooks();
		} else {
			$hooks = explode( ',', $hooks );
		}

		foreach ( $hooks as $hook ) {
			$row = $args = array( 'hook' => $hook );

			if ( in_array( 'total', $columns ) ) {
				$row['total'] = $store->query_actions( array( 'hook' => $hook ), 'count' );
			}

			if ( count( array_intersect( $stati, $columns ) ) ) {
				foreach ( array_intersect( $columns, $stati ) as $status ) {
					$args['status'] = $status;
					$count = $store->query_actions( $args, 'count' );;
					$row[ $status ] = $count;
				}
			}

			if ( count( array_intersect( array( 'oldest', 'newest' ), $columns ) ) ) {
				foreach ( array( 'oldest', 'newest' ) as $point ) {
					$action = $store->query_actions( array(
						'hook' => $hook,
						'claimed'  => false,
						'per_page' => 1,
						'order'    => ( 'oldest' === $point ? 'ASC' : 'DESC' ),
					) );

					if ( ! empty( $action ) ) {
						$date_object = $store->get_date( $action[0] );
						$row[ $point ] = $date_object->format( 'Y-m-d H:i:s O' );
					}
				}
			}

			$rows[] = $row;
		}

		$this->table( $rows, $columns );
	}

	/**
	 * Print counts for all statuses.
	 */
	protected function default() {
		$store = ActionScheduler::store();
		$columns = explode( ',', \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'columns', 'status,count' ) );
		$status_labels = $store->get_status_labels();
		$action_counts = $store->action_counts();
		$status_dates = $store->action_dates();

		$rows = array();
		$total = 0;

		foreach ( $status_labels as $post_status => $label ) {
			$rows[] = array(
				'status' => $label,
				'count' => $action_counts[ $post_status ],
				'oldest' => $status_dates[ $post_status ]['oldest'],
				'newest' => $status_dates[ $post_status ]['newest'],
			);

			$total += $action_counts[ $post_status ];
		}

		$this->table( $rows, $columns );
		$this->log( 'Total: ' . $total );
	}

}
