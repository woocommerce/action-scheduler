<?php

/**
 * WP CLI command to get Action Scheduler versions.
 */
class ActionScheduler_WPCLI_Command_Version extends ActionScheduler_Abstract_WPCLI_Command {

	/**
	 * Execute command.
	 */
	public function execute() {
		$all = (bool) \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'all', false );
		$helper = ActionScheduler_Versions::instance();

		if ( $all ) {
			$this->print_all( $helper->get_versions() );
		} else {
			$this->log( $helper->latest_version() );
		}
	}

	/**
	 * Print table of versions.
	 */
	protected function print_all( $versions ) {
		$versions = array_keys( $versions );
		usort( $versions, 'version_compare' );

		$items = array();

		foreach ( $versions as $version ) {
			$items[] = array(
				'version' => $version
			);
		}

		$this->table( $items, 'version' );
	}

}
