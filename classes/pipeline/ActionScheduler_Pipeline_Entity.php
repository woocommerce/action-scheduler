<?php

trait ActionScheduler_Pipeline_Entity {
	/**
	 * @var ActionScheduler_Pipeline_Janitor
	 */
	private $janitor;

	/**
	 * @var int
	 */
	private $uid;

	/**
	 * Initialize the pipeline server.
	 *
	 * @throws ActionScheduler_Pipeline_Exception
	 *
	 * @return $this
	 */
	public function init() {
		$schema = new ActionScheduler_PipelineSchema();
		$schema->ensure_is_available();

		$this->janitor = new ActionScheduler_Pipeline_Janitor();
		$this->confirm_store_type();
		$this->get_claim_id();

		return $this;
	}

	/**
	 * @throws ActionScheduler_Pipeline_Exception
	 *
	 * @return void
	 */
	private function confirm_store_type() {
		if ( ! is_a( ActionScheduler_Store::instance(), ActionScheduler_DBStore::class ) ) {
			throw new ActionScheduler_Pipeline_Exception(
				__( 'A pipeline can only be created when the DBStore is available.', 'action-scheduler' ),
				ActionScheduler_Pipeline_Exception::INCOMPATIBLE_ACTION_STORE
			);
		}
	}

	/**
	 * @return void
	 */
	private function get_claim_id() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->actionscheduler_claims,
			[ 'date_created_gmt' => as_get_datetime_object()->format( 'Y-m-d H:i:s' ) ]
		);

		$this->claim_id = (int) $wpdb->insert_id;
	}

	private function register_self( string $type ) {
		global $wpdb;

		if ( $type !== 'server' && $type !== 'client' ) {
			throw new ActionScheduler_Pipeline_Exception(
				__( 'Entity type must be either "server" or "client".', 'action-scheduler' ),
				ActionScheduler_Pipeline_Exception::BAD_ENTITY_TYPE
			);
		}

		$inserted = $wpdb->insert(
			$wpdb->actionscheduler_pipeline_entities,
			[
				'type'   => $type,
				'status' => 'idle',
				'pid'    => (int) getmypid(),
			]
		);

		if ( ! $inserted ) {
			throw new ActionScheduler_Pipeline_Exception(
				__( 'Unable to register entity.', 'action-scheduler' ),
				ActionScheduler_Pipeline_Exception::COULD_NOT_REGISTER_ENTITY
			);
		}

		$this->uid = (int) $wpdb->insert_id;
	}

	/**
	 * Updates the status and the 'alive' timestamp.
	 *
	 * @throws ActionScheduler_Pipeline_Exception
	 *
	 * @param string $status
	 *
	 * @return void
	 *
	 */
	private function update_status( string $status ) {
		global $wpdb;

		if ( $status !== 'idle' && $status !=='busy' && $status !== 'ended' ) {
			throw new ActionScheduler_Pipeline_Exception(
				__( 'Entity status must be "idle", "busy" or "ended".', 'action-scheduler' ),
				ActionScheduler_Pipeline_Exception::BAD_STATUS
			);
		}

		$wpdb->query(
			$wpdb->prepare(
				"
					UPDATE {$wpdb->actionscheduler_pipeline_entities}
					SET    status = %s,
					       alive = UNIX_TIMESTAMP()
					WHERE  uid = %d
				",
				$status,
				$this->uid
			)
		);
	}
}
