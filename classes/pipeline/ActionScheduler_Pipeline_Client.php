<?php

class ActionScheduler_Pipeline_Client extends ActionScheduler_Abstract_QueueRunner {
	use ActionScheduler_Pipeline_Entity;

	const PIPELINE_CONTEXT = 'Pipeline';

	/**
	 * @var int
	 */
	private $next_action_id;

	/**
	 * @var int
	 */
	private $next_action_message_id;

	/**
	 * @var int
	 */
	private $processed = 0;

	/**
	 * Essentially an alias of the start() method, added to facilitate use of abstract base-class
	 * functionality.
	 *
	 * @throws ActionScheduler_Pipeline_Exception
	 *
	 * @param string $context Unused.
	 *
	 * @return int The number of actions processed.
	 */
	public function run( $context = '' ) {
		$this->start();
		return $this->processed;
	}

	/**
	 * @throws ActionScheduler_Pipeline_Exception
	 *
	 * @return void
	 */
	public function start() {
		$this->register_self( 'client' );
		$this->update_status( 'busy' );
		$this->loop();
	}

	/**
	 * @throws ActionScheduler_Pipeline_Exception
	 *
	 * @return void
	 */
	private function loop() {
		while ( $this->should_continue() ) {
			$this->update_status( 'idle' );
			$this->wait_on_next_action_request();
			$this->update_status( 'busy' );
			$this->process_action( $this->next_action_id, self::PIPELINE_CONTEXT );
			$this->processed++;
			$this->mark_done();
		}
	}


	private function mark_done() {
		global $wpdb;

		$wpdb->update(
			$wpdb->actionscheduler_pipeline_messages,
			[ 'status' => 'done' ],
			[ 'uid' => $this->next_action_message_id ]
		);
	}


	/**
	 * Shutdown upon request.
	 *
	 * @throws ActionScheduler_Pipeline_Exception
	 *
	 * @return bool
	 */
	private function should_continue(): bool {
		global $wpdb;

		$shutdown_requested = (bool) $wpdb->get_var( "
			SELECT COUNT(*)
			FROM   {$wpdb->actionscheduler_pipeline_messages}
			WHERE  target_entity = {$this->uid}
			       AND type = 'shutdown'
		" );

		if ( ! $shutdown_requested ) {
			return true;
		}

		$this->shutdown();
		$this->update_shutdown_request();
		return false;
	}

	/**
	 * Execute shutdown procedure.
	 *
	 * @throws ActionScheduler_Pipeline_Exception
	 *
	 * @return void
	 */
	private function shutdown() {
		$this->reject_outstanding_requests();
		$this->update_status( 'ended' );
	}

	/**
	 * Any unprocessed actions sent to this client should be rejected.
	 *
	 * @return void
	 */
	private function reject_outstanding_requests() {
		global $wpdb;

		$wpdb->query( "
			UPDATE {$wpdb->actionscheduler_pipeline_messages}
			SET    status = 'rejected'
			WHERE  target_entitity = {$this->uid}
			       AND status NOT IN ( 'rejected', 'done' )
			       AND type = 'action'
		" );
	}

	/**
	 * Indicate the shutdown was successful.
	 *
	 * @return void
	 */
	private function update_shutdown_request() {
		global $wpdb;

		$wpdb->query( "
			UPDATE {$wpdb->actionscheduler_pipeline_messages}
			SET    status = 'done'
			WHERE  target_entitity = {$this->uid}
			       AND type = 'shutdown'
		" );
	}

	/**
	 * @return void
	 */
	private function wait_on_next_action_request() {
		$delay                        = new ActionScheduler_Pipeline_LoopDelay();
		$this->next_action_id         = 0;
		$this->next_action_message_id = 0;

		do {
			$delay->wait();
			$this->get_next_action_request();
		} while ( $this->next_action_id === 0 );
	}

	/**
	 * @return void
	 */
	private function get_next_action_request() {
		global $wpdb;

		$message = $wpdb->get_row( "
			SELECT uid, target_action
			FROM   {$wpdb->actionscheduler_pipeline_messages}
			WHERE  target_entity = {$this->uid}
			       AND type = 'action'
			       AND status = 'waiting'
		" );

		if ( null === $message ) {
			return;
		}

		$this->next_action_id         = $message->target_action;
		$this->next_action_message_id = $message->uid;
	}
}
