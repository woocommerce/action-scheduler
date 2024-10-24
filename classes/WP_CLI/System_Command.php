<?php

namespace Action_Scheduler\WP_CLI;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaping output is not necessary in WP CLI.

use function \WP_CLI\Utils\get_flag_value;

/**
 * System info WP-CLI commands for Action Scheduler.
 */
class System_Command {

	/**
	 * Data store for querying actions
	 *
	 * @var ActionScheduler_Store
	 */
	protected $store;

	/**
	 * Construct.
	 */
	public function __construct() {
		$this->store = \ActionScheduler::store();
	}

	/**
	 * Print in-use data store class.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Keyed args.
	 * @return void
	 *
	 * @subcommand data-store
	 */
	public function datastore( array $args, array $assoc_args ) {
		echo $this->get_current_datastore();
	}

	/**
	 * Print in-use runner class.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Keyed args.
	 * @return void
	 */
	public function runner( array $args, array $assoc_args ) {
		echo $this->get_current_runner();
	}

	/**
	 * Get system status.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Keyed args.
	 * @return void
	 */
	public function status( array $args, array $assoc_args ) {
		/**
		 * Get runner status.
		 *
		 * @link https://github.com/woocommerce/action-scheduler-disable-default-runner
		 */
		$runner_enabled = has_action( 'action_scheduler_run_queue', array( \ActionScheduler::runner(), 'run' ) );

		\WP_CLI::line( sprintf( 'Data store: %s', $this->get_current_datastore() ) );
		\WP_CLI::line( sprintf( 'Runner: %s%s', $this->get_current_runner(), ( $runner_enabled ? '' : ' (disabled)' ) ) );
		\WP_CLI::line( sprintf( 'Version: %s', $this->get_latest_version() ) );

		$rows              = array();
		$action_counts     = $this->store->action_counts();
		$oldest_and_newest = $this->get_oldest_and_newest( array_keys( $action_counts ) );

		foreach ( $action_counts as $status => $count ) {
			$rows[] = array(
				'status' => $status,
				'count'  => $count,
				'oldest' => $oldest_and_newest[ $status ]['oldest'],
				'newest' => $oldest_and_newest[ $status ]['newest'],
			);
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, array( 'status', 'count', 'oldest', 'newest' ) );
		$formatter->display_items( $rows );
	}

	/**
	 * Display the active version, or all registered versions.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : List all registered versions.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Keyed args.
	 * @return void
	 */
	public function version( array $args, array $assoc_args ) {
		$all      = (bool) get_flag_value( $assoc_args, 'all' );
		$instance = \ActionScheduler_Versions::instance();
		$latest   = $this->get_latest_version( $instance );

		if ( $all ) {
			$versions = $instance->get_versions();

			$rows = array();

			foreach ( $versions as $version => $callback ) {
				$active = 'no';

				if ( $version === $latest ) {
					$active = 'yes';
				}

				$rows[ $version ] = array(
					'version'  => $version,
					'callback' => $callback,
					'active'   => $active,
				);
			}

			uksort( $rows, 'version_compare' );

			$formatter = new \WP_CLI\Formatter( $assoc_args, array( 'version', 'callback', 'active' ) );
			$formatter->display_items( $rows );

			return;
		}

		echo $latest;
	}

	/**
	 * Get current data store.
	 *
	 * @return string
	 */
	protected function get_current_datastore() {
		return get_class( $this->store );
	}

	/**
	 * Get latest version.
	 *
	 * @param null|\ActionScheduler_Versions $instance Versions.
	 * @return string
	 */
	protected function get_latest_version( $instance = null ) {
		if ( is_null( $instance ) ) {
			$instance = \ActionScheduler_Versions::instance();
		}

		return $instance->latest_version();
	}

	/**
	 * Get current runner.
	 *
	 * @return string
	 */
	protected function get_current_runner() {
		return get_class( \ActionScheduler::runner() );
	}

	/**
	 * Get oldest and newest scheduled dates for a given set of statuses.
	 *
	 * @param array $status_keys Set of statuses to find oldest & newest action for.
	 * @return array
	 */
	protected function get_oldest_and_newest( $status_keys ) {
		$oldest_and_newest = array();

		foreach ( $status_keys as $status ) {
			$oldest_and_newest[ $status ] = array(
				'oldest' => '&ndash;',
				'newest' => '&ndash;',
			);

			if ( 'in-progress' === $status ) {
				continue;
			}

			$oldest_and_newest[ $status ]['oldest'] = $this->get_action_status_date( $status, 'oldest' );
			$oldest_and_newest[ $status ]['newest'] = $this->get_action_status_date( $status, 'newest' );
		}

		return $oldest_and_newest;
	}

	/**
	 * Get oldest or newest scheduled date for a given status.
	 *
	 * @param string $status Action status label/name string.
	 * @param string $date_type Oldest or Newest.
	 * @return string
	 */
	protected function get_action_status_date( $status, $date_type = 'oldest' ) {
		$order = 'oldest' === $date_type ? 'ASC' : 'DESC';

		$args = array(
			'claimed'  => false,
			'status'   => $status,
			'per_page' => 1,
			'order'    => $order,
		);

		$action = $this->store->query_actions( $args );

		if ( ! empty( $action ) ) {
			$date_object = $this->store->get_date( $action[0] );
			$action_date = $date_object->format( 'Y-m-d H:i:s O' );
		} else {
			$action_date = '&ndash;';
		}

		return $action_date;
	}

}
