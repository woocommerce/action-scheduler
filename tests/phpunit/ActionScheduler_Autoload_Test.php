<?php
/**
 * Autoloader regression tests.
 *
 * @package ActionScheduler
 */

// Cold-process tests require PHP subprocesses, pipe I/O, and PHP literals for their inputs.
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open, WordPress.WP.AlternativeFunctions, WordPress.PHP.DevelopmentFunctions.error_log_var_export

/**
 * Autoloader tests use fresh PHP processes because the WordPress bootstrap loads migration classes.
 */
class ActionScheduler_Autoload_Test extends PHPUnit\Framework\TestCase {

	/**
	 * Run the real SPL autoloader without initializing the WordPress runtime.
	 *
	 * @param string $code PHP assertions to execute.
	 */
	private function run_autoloader( $code ) {
		$root    = var_export( dirname( __DIR__, 2 ), true );
		$process = proc_open( escapeshellarg( PHP_BINARY ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
		$this->assertIsResource( $process );
		fwrite(
			$pipes[0],
			'<?php
			define( "ABSPATH", ' . $root . ' );
			function trailingslashit( $path ) { return rtrim( $path, "/" ) . "/"; }
			class WP_CLI_Command {}
			require ' . $root . ' . "/classes/abstracts/ActionScheduler.php";
			$property = new ReflectionProperty( "ActionScheduler", "plugin_file" );
			$property->setAccessible( true );
			$property->setValue( null, ' . $root . ' . "/action-scheduler.php" );
			spl_autoload_register( array( "ActionScheduler", "autoload" ) );
			' . $code . "\n"
		);
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$errors = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$this->assertSame( 0, proc_close( $process ), $output . $errors );
		$this->assertSame( 'ok', $output, $errors );
	}

	/**
	 * Unrelated global requests must not include files, before or after migration is loaded.
	 */
	public function test_foreign_lookups_do_not_include_files() {
		$this->run_autoloader(
			'foreach ( array( false, true ) as $load_migration ) {
				if ( $load_migration ) { class_exists( "Action_Scheduler\\\\Migration\\\\Controller" ); }
				$files = get_included_files();
				foreach ( array(
					"Controller", "Scheduler", "Config", "Runner", "BatchFetcher", "ActionMigrator", "LogMigrator",
					"DryRun_ActionMigrator", "DryRun_LogMigrator", "ProgressBar", "Migration_Command", "Action_Command", "System_Command",
					"OtherDeprecated", "OtherSchedule", "OtherAction", "OtherSchema", "ActionSchedulerExtra"
				) as $class_name ) {
					if ( class_exists( $class_name ) || class_exists( $class_name ) || $files !== get_included_files() ) { exit( 1 ); }
				}
			}
			echo "ok";'
		);
	}

	/**
	 * A lookalike namespace prefix must not load the migration Controller.
	 */
	public function test_namespace_prefix_requires_a_separator() {
		$this->run_autoloader(
			'$files = get_included_files();
			$class_name = "Action_SchedulerExtra\\\\Migration\\\\Controller";
			if ( class_exists( $class_name ) || class_exists( $class_name ) || $files !== get_included_files() ) { exit( 1 ); }
			echo "ok";'
		);
	}

	/**
	 * Another plugin can supply a global Controller with either SPL registration order.
	 */
	public function test_other_autoloaders_can_supply_classes() {
		foreach ( array( false, true ) as $prepend ) {
			$this->run_autoloader(
				'class_exists( "Action_Scheduler\\\\Migration\\\\Controller" );
				spl_autoload_register( function ( $class_name ) {
					if ( "Controller" === $class_name ) { eval( "class Controller {}" ); }
				}, true, ' . var_export( $prepend, true ) . ' );
				if ( ! class_exists( "Controller" ) || ! class_exists( "Controller" ) ) { exit( 1 ); }
				echo "ok";'
			);
		}
	}

	/**
	 * Supported classes must load before any other Action Scheduler class has been requested.
	 *
	 * @dataProvider supported_classes
	 * @param string $class_name Supported class or interface.
	 */
	public function test_supported_classes_load( $class_name ) {
		$this->run_autoloader(
			'$class_name = ' . var_export( $class_name, true ) . ';
			if ( ! class_exists( $class_name ) && ! interface_exists( $class_name ) ) { exit( 1 ); }
			echo "ok";'
		);
	}

	/**
	 * Representative classes from each supported route.
	 *
	 * @return array
	 */
	public function supported_classes() {
		return array_map(
			function ( $class_name ) {
				return array( $class_name );
			},
			array(
				'ActionScheduler_ActionFactory',
				'ActionScheduler_Store',
				'ActionScheduler_SimpleSchedule',
				'ActionScheduler_Action',
				'ActionScheduler_StoreSchema',
				'ActionScheduler_DBStore',
				'ActionScheduler_DBStoreMigrator',
				'ActionScheduler_Store_Deprecated',
				'ActionScheduler_WPCLI_QueueRunner',
				'CronExpression',
				'CronExpression_FieldInterface',
				'WP_Async_Request',
				'Action_Scheduler\Migration\Controller',
				'Action_Scheduler\migration\Controller',
				'Action_Scheduler\MIGRATION\Runner',
				'Action_Scheduler\WP_CLI\Migration_Command',
				'Action_Scheduler\WP_CLI\Action_Command',
				'Action_Scheduler\WP_CLI\System_Command',
				'Action_Scheduler\wp_cli\ProgressBar',
			)
		);
	}
}
