<?php


namespace Action_Scheduler\WP_CLI;

use Action_Scheduler\Migration\Config;
use Action_Scheduler\Migration\Runner;
use Action_Scheduler\Migration\Scheduler;
use Action_Scheduler\Migration\Controller;
use WP_CLI;

/**
 * Class Migration_Command
 *
 * @package Action_Scheduler\WP_CLI
 *
 * @since 3.0.0
 *
 * @codeCoverageIgnore
 */
class Migration_Command extends \ActionScheduler_Abstract_WPCLI_Command {

	/** @var int */
	private $total_processed = 0;

	/**
	 * Process the data migration.
	 *
	 * @return void
	 */
	public function execute() {
		if ( \ActionScheduler_DataController::is_migration_complete() ) {
			WP_CLI::success( sprintf( 'No migration required. Data store is %s.', get_class( \ActionScheduler_Store::instance() ) ) );
			return;
		}

		if ( ! Controller::instance()->allow_migration() ) {
			WP_CLI::error( 'Migration prevented due to missing depdendencies or by current data store.' );
			return;
		}

		$this->init_logging();

		$config = $this->get_migration_config( $assoc_args );
		$runner = new Runner( $config );
		$runner->init_destination();

		$batch_size = isset( $assoc_args[ 'batch-size' ] ) ? (int) $assoc_args[ 'batch-size' ] : 100;
		$free_on    = isset( $assoc_args[ 'free-memory-on' ] ) ? (int) $assoc_args[ 'free-memory-on' ] : 50;
		$sleep      = isset( $assoc_args[ 'pause' ] ) ? (int) $assoc_args[ 'pause' ] : 0;
		\ActionScheduler_DataController::set_free_ticks( $free_on );
		\ActionScheduler_DataController::set_sleep_time( $sleep );

		do {
			$actions_processed     = $runner->run( $batch_size );
			$this->total_processed += $actions_processed;
		} while ( $actions_processed > 0 );

		if ( ! $config->get_dry_run() ) {
			// let the scheduler know that there's nothing left to do
			$scheduler = new Scheduler();
			$scheduler->mark_complete();
		}

		WP_CLI::success( sprintf( '%s complete. %d actions processed.', $config->get_dry_run() ? 'Dry run' : 'Migration', $this->total_processed ) );
	}

	/**
	 * Build the config object used to create the Runner
	 *
	 * @param array $args Optional arguments.
	 *
	 * @return ActionScheduler\Migration\Config
	 */
	private function get_migration_config( $args ) {
		$args = wp_parse_args( $args, [
			'dry-run' => false,
		] );

		$config = Controller::instance()->get_migration_config_object();
		$config->set_dry_run( ! empty( $args[ 'dry-run' ] ) );

		return $config;
	}

	/**
	 * Hook command line logging into migration actions.
	 */
	private function init_logging() {
		add_action( 'action_scheduler/migrate_action_dry_run', function ( $action_id ) {
			WP_CLI::debug( sprintf( 'Dry-run: migrated action %d', $action_id ) );
		}, 10, 1 );
		add_action( 'action_scheduler/no_action_to_migrate', function ( $action_id ) {
			WP_CLI::debug( sprintf( 'No action found to migrate for ID %d', $action_id ) );
		}, 10, 1 );
		add_action( 'action_scheduler/migrate_action_failed', function ( $action_id ) {
			WP_CLI::warning( sprintf( 'Failed migrating action with ID %d', $action_id ) );
		}, 10, 1 );
		add_action( 'action_scheduler/migrate_action_incomplete', function ( $source_id, $destination_id ) {
			WP_CLI::warning( sprintf( 'Unable to remove source action with ID %d after migrating to new ID %d', $source_id, $destination_id ) );
		}, 10, 2 );
		add_action( 'action_scheduler/migrated_action', function ( $source_id, $destination_id ) {
			WP_CLI::debug( sprintf( 'Migrated source action with ID %d to new store with ID %d', $source_id, $destination_id ) );
		}, 10, 2 );
		add_action( 'action_scheduler/migration_batch_starting', function ( $batch ) {
			WP_CLI::debug( 'Beginning migration of batch: ' . print_r( $batch, true ) );
		}, 10, 1 );
		add_action( 'action_scheduler/migration_batch_complete', function ( $batch ) {
			WP_CLI::log( sprintf( 'Completed migration of %d actions', count( $batch ) ) );
		}, 10, 1 );
	}
}
