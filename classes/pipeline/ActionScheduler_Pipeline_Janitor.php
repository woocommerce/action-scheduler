<?php

/**
 * @todo check actions table for stalls/timeouts
 * @todo dead client timeout should be configurable
 */
class ActionScheduler_Pipeline_Janitor {
	public function clean() {
		$this->remove_complete_and_rejected_messages();
		$this->remove_dead_clients();
	}

	/**
	 * Remove messages that have been updated to a status of rejected or done.
	 *
	 * @return void
	 */
	private function remove_complete_and_rejected_messages() {
		global $wpdb;

		$wpdb->query( "
			DELETE FROM {$wpdb->actionscheduler_pipeline_messages}
			WHERE status IN ( 'rejected', 'done' )
		" );
	}

	/**
	 * Remove clients that have ended, or clients that are marked as busy/idle
	 * but have not shown signs of life in the last 20 minutes.
	 *
	 * @return void
	 */
	private function remove_dead_clients() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"
					DELETE FROM {$wpdb->actionscheduler_pipeline_entities}
					WHERE type = 'client'
					      AND (
					          status = 'ended'
					          OR UNIX_TIMESTAMP() - alive > %d
					      )

				",
				20 * MINUTE_IN_SECONDS
			)
		);
	}
}
