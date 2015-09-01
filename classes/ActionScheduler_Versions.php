<?php

/**
 * Class ActionScheduler_Versions
 */
class ActionScheduler_Versions {

	/**
	 * @var ActionScheduler_Versions
	 */
	private static $instance = null;

	private $versions      = array();
	private $version_key   = '1.4-dev';
	private $version_value = 'action_scheduler_initialize_1_dot_4_dev';

	/**
	 * @return ActionScheduler_Versions
	 * @codeCoverageIgnore
	 */
	public static function instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function initialize_latest_version() {
		$self = self::instance();

		// Must be the version identified below
		$self->action_scheduler_register_1_dot_4_dev();

		$latest_version = $self->latest_version_callback();

		if ( ! empty( $latest_version ) ) {
			$self->$latest_version();
		}
	}

	/**
	 * Register the current version of the Action Scheduler
	 */
	public function action_scheduler_register_1_dot_4_dev() {
		$this->register( $this->version_key, $this->version_value );
	}

	/**
	 * Load the current version of the Action Scheduler
	 */
	public function action_scheduler_initialize_1_dot_4_dev() {
		$d = DIRECTORY_SEPARATOR;
		require_once( 'ActionScheduler.php' );
		ActionScheduler::init( dirname( __DIR__ ) . $d . 'action-scheduler.php' );
	}

	public function register( $version_string, $initialization_callback ) {
		if ( isset( $this->versions[ $version_string ] ) ) {
			return false;
		}

		$this->versions[ $version_string ] = $initialization_callback;

		return true;
	}

	public function get_versions() {
		return $this->versions;
	}

	public function latest_version() {
		if ( is_array( $this->versions ) ) {
			$keys = array_keys( $this->versions );
			if ( empty( $keys ) ) {
				return false;
			}

			uasort( $keys, 'version_compare' );

			return end( $keys );
		}

		return false;
	}

	public function latest_version_callback() {
		$latest = $this->latest_version();

		if ( empty( $latest ) || ! isset( $this->versions[ $latest ] ) ) {
			return '__return_null';
		}

		return $this->versions[ $latest ];
	}
}
