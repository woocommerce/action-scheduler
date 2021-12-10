<?php

class ActionScheduler_Pipeline_Server {
	use ActionScheduler_Pipeline_Entity;

	/**
	 * @var int[]
	 */
	private $actions;

	/**
	 * @var int
	 */
	private $claim_id;

	/**
	 * @var int[]
	 */
	private $clients;

	/**
	 * @var int
	 */
	private $last_ping = 0;

	/**
	 * Start the pipeline server.
	 *
	 * @throws ActionScheduler_Pipeline_Exception
	 *
	 * @return void
	 */
	public function start() {
		$this->check_for_existing_servers();
		$this->register_self( 'server' );
		$this->update_status( 'busy' );
		$this->loop();
	}

	/**
	 * Detect and warn in the event that other potentially active servers exist.
	 *
	 * @throws ActionScheduler_Pipeline_Exception
	 *
	 * @return void
	 */
	public function check_for_existing_servers() {
		global $wpdb;

		$possibly_active_servers = $wpdb->get_results( "
			SELECT uid,
			       pid,
			       UNIX_TIMESTAMP() - alive AS last_ping
			FROM   {$wpdb->actionscheduler_pipeline_entities}
			WHERE  type = 'server'
			       AND status <> 'ended'
		" );

		if ( count( $possibly_active_servers ) === 0 ) {
			return;
		}

		$server_details = [];

		foreach ( $possibly_active_servers as $server ) {
			$server_details[] = sprintf(
				/* translators: %1$d is a numeric ID, %2$d is a numeric ID, %3$d is a number of seconds. */
				__( 'UID %1$d (PID %2$d - last active %3$ds ago)', 'action-scheduler' ),
				$server->uid,
				$server->pid,
				$server->last_ping
			);
		}

		throw new ActionScheduler_Pipeline_Exception(
			sprintf(
				/* translators: %1$s is a list of server details. */
				__( 'Other servers may still be running: %1$s', 'action-scheduler' ),
				implode( ' | ', $server_details )
			),
			ActionScheduler_Pipeline_Exception::ONLY_ONE_SERVER_ALLOWED
		);
	}

	/**
	 * Manages pushing jobs to available clients.
	 *
	 * @return void
	 */
	private function loop() {
		$delay = new ActionScheduler_Pipeline_LoopDelay( ActionScheduler_Pipeline_LoopDelay::DELAY_STAYS_CONSTANT );
		$ticks = 0;

		while ( true ) {
			$ticks++;
			$delay->wait();

			$this->wait_on_available_clients();
			$this->wait_on_pending_actions();
			$this->update_status( 'busy' );

			$this->distribute_actions();
			$this->wait_on_client_acceptance();

			if ( $ticks % 50 === 0 ) {
				$this->janitor->clean();
			}

			$this->update_status( 'idle' );
		}
	}

	/**
	 * @return void
	 */
	private function wait_on_available_clients() {
		$delay = new ActionScheduler_Pipeline_LoopDelay();

		do {
			$delay->wait(); // First delay is 0s.
			$this->get_available_clients();
		} while ( empty( $this->clients ) );
	}

	/**
	 * @return void
	 */
	private function get_available_clients() {
		global $wpdb;

		$available_client_ids = $wpdb->get_col( "
			SELECT uid
			FROM   {$wpdb->actionscheduler_pipeline_entities}
			WHERE  type = 'client'
                   AND status = 'idle'
		" );

		$this->clients = array_map( 'absint', $available_client_ids );
	}

	private function wait_on_pending_actions() {
		$delay = new ActionScheduler_Pipeline_LoopDelay();

		do {
			$delay->wait();
			$this->claim_actions();
		} while ( empty( $this->actions ) );
	}

	/**
	 * @todo consider NOT_IN clause to identify action IDs that are already posted but still waiting, etc.
	 *
	 * @return void
	 */
	private function claim_actions() {
		global $wpdb;

		$how_many  = count( $this->clients );
		$now_gmt   = wp_date( 'Y-m-d H:i:s', null, timezone_open( 'GMT' ) );
		$now_local = wp_date( 'Y-m-d H:i:s' );

		// Stake our claim first of all (a simplified version of the equivalent
		// logic found in ActionScheduler_DBStore).
		$wpdb->query(
			$wpdb->prepare(
				"
					UPDATE {$wpdb->actionscheduler_actions}

					SET claim_id = %d,
					    last_attempt_gmt = %s,
					    last_attempt_local = %s

					WHERE claim_id = 0
					      AND scheduled_date_gmt <= %s
					      AND status = %s

					ORDER BY attempts ASC,
					         scheduled_date_gmt ASC,
					         action_id ASC

					LIMIT %d
				",
				$this->claim_id,
				$now_gmt,
				$now_local,
				$now_gmt,
				ActionScheduler_Store::STATUS_PENDING,
				$how_many
			)
		);

		// Now pull the IDs of our claimed actions (ignoring any for which we have
		// already posted messages)
		$action_ids = $wpdb->get_col(
			$wpdb->prepare(
				"
					SELECT action_id
					FROM   {$wpdb->actionscheduler_actions}
					WHERE  claim_id = %d
					       AND action_id NOT IN (
					           SELECT target_action
					           FROM   {$wpdb->actionscheduler_pipeline_messages} AS messages
					           WHERE  type = 'action'
					       )
				",
				$this->claim_id
			)
		);

		$this->actions = array_map( 'absint', $action_ids );
	}

	/**
	 * @return void
	 */
	private function distribute_actions() {
		global $wpdb;

		$row_values = [];
		reset( $this->clients );

		foreach ( $this->actions as $action_id ) {
			$row_values[] = $wpdb->prepare( "( %d, 'action', 'waiting', %d )", current( $this->clients ), $action_id );
			next( $this->clients );
		}

		$values = 'VALUES ' . implode( ",\n ", $row_values );

		$wpdb->query( "
			INSERT INTO {$wpdb->actionscheduler_pipeline_messages}
			( target_entity, type, status, target_action )
			{$values}
		" );
	}

	/**
	 * Waits on an indication that at least one available client has started
	 * processing one of the recently claimed actions.
	 *
	 * @return void
	 */
	private function wait_on_client_acceptance() {
		$delay = new ActionScheduler_Pipeline_LoopDelay();
		$delay->reset( 1 );

		do {
			$delay->wait();
			// @todo warn if we don't observe acceptance after x ticks
			// @todo add option to reassign messages to a functional client
		} while ( ! $this->observed_client_acceptance_of_actions() );
	}

	/**
	 * Indicates if at least one of our available clients has started processing one
	 * of our pending actions.
	 *
	 * @return bool
	 */
	private function observed_client_acceptance_of_actions(): bool {
		global $wpdb;

		$action_id_list = implode(
			',',
			array_map(
				function ( int $id ) { return "'{$id}'"; },
				$this->actions
			)
		);

		return (bool) $wpdb->get_var( "
			SELECT COUNT(*)
			FROM   {$wpdb->actionscheduler_pipeline_messages}
			WHERE  target_action IN ( {$action_id_list} )
                   AND status <> 'waiting'
		" );
	}
}
