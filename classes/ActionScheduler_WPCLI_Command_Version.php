<?php

/**
 * WP CLI command to get Action Scheduler versions.
 */
class ActionScheduler_WPCLI_Command_Version extends ActionScheduler_Abstract_WPCLI_Command {

	/**
	 * @var array
	 */
	protected $component_types = array(
		'mu-plugins' => 'mu-plugin',
		'plugins' => 'plugin',
		'themes' => 'theme',
	);

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
		$items = $version_strings = array();

		foreach ( $versions as $version => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$reflection = new ReflectionFunction( $callback );
				$reflection_filepath = $reflection->getFileName();
				$is_active = dirname( $reflection_filepath ) === ActionScheduler::plugin_path( '' );

				$items[] = array(
					'version' => $version,
					'callback' => $callback,
					'component' => $this->get_component( $reflection_filepath ),
					'active' => $is_active ? 'X' : '',
				);

				$version_strings[] = $version;
			}
		}

		// array_multisort( array_column( $items, 'version' ), $items );

		$this->table( $items, array( 'version', 'component', 'active' ) );
	}

	/**
	 * Get components of registered versions.
	 *
	 * @param string $filepath
	 * @return string
	 */
	protected function get_component( $filepath ) {
		$filepath = str_replace( trailingslashit( WP_CONTENT_DIR ), '', $filepath );
		$filepath_pieces = explode( '/', $filepath );

		$type = 'unknown';

		if ( array_key_exists( $filepath_pieces[0], $this->component_types ) ) {
			$type = $this->component_types[ $filepath_pieces[0] ];
		}

		return $type . ': ' . $filepath_pieces[1];
	}

}
