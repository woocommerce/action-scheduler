<?php

class ActionScheduler_PipelineSchema extends ActionScheduler_Abstract_Schema {
	const ENTITIES_TABLE = 'actionscheduler_pipeline_entities';
	const MESSAGES_TABLE = 'actionscheduler_pipeline_messages';

	public function __construct() {
		$this->tables = [
			self::ENTITIES_TABLE,
			self::MESSAGES_TABLE,
		];
	}

	/**
	 * A fast check to see if the required pipeline tables are available, creating them
	 * if one or both is missing.
	 *
	 * @return void
	 */
	public function ensure_is_available() {
		global $wpdb;

		$existing_tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
		$create          = false;

		foreach ( $this->tables as $table ) {
			$table = $wpdb->prefix . $table;

			if ( ! in_array( $table, $existing_tables ) ) {
				$create = true;
				break;
			}
		}

		$this->register_tables( $create );
	}

	protected function get_table_definition( $table ) {
		global $wpdb;
		$table_name      = $wpdb->$table;
		$charset_collate = $wpdb->get_charset_collate();
		$entities_table  = $wpdb->prefix . self::ENTITIES_TABLE;

		switch ( $table ) {
			case self::ENTITIES_TABLE:
				return "
					CREATE TABLE {$table_name} (
						uid    bigint( 20 ) unsigned NOT NULL auto_increment,
						type   enum ( 'server', 'client' ) NOT NULL,
						status enum ( 'idle', 'busy', 'ended' ) NOT NULL,
						pid    int NOT NULL,
						alive  bigint NOT NULL DEFAULT 0,

						PRIMARY KEY ( uid )
					) $charset_collate
				";

			case self::MESSAGES_TABLE;
				return "
					CREATE TABLE {$table_name} (
						uid           bigint( 20 ) unsigned NOT NULL auto_increment,
						target_entity bigint( 20 ) unsigned NOT NULL,
						type          enum ( 'action', 'shutdown' ) NOT NULL,
						status        enum ( 'waiting', 'in-progress', 'rejected', 'done' ) NOT NULL,
						target_action bigint( 20 ) unsigned NOT NULL,

						PRIMARY KEY ( uid ),
						FOREIGN KEY ( target_entity ) REFERENCES {$entities_table} ( uid )
					) $charset_collate
				";
		}
	}
}
