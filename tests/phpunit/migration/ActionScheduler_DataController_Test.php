<?php

/**
 * Class ActionScheduler_DataController_Test
 *
 * @group migration
 */
class ActionScheduler_DataController_Test extends ActionScheduler_UnitTestCase {
	/**
	 * The main plugin file of the running copy of Action Scheduler, restored after each test.
	 *
	 * @var string
	 */
	private $original_plugin_file;

	/**
	 * Perform test set-up work.
	 */
	public function set_up() {
		parent::set_up();
		$this->original_plugin_file = $this->set_plugin_file( null );
		ActionScheduler_DataController::mark_migration_complete();
	}

	/**
	 * Perform test tear-down work.
	 */
	public function tear_down() {
		$this->set_plugin_file( $this->original_plugin_file );
		parent::tear_down();
	}

	/**
	 * Deactivating a plugin that does not provide the running copy of Action Scheduler (embedded in host-plugin)
	 * leaves the flag intact.
	 *
	 * @dataProvider provide_unrelated_plugins
	 *
	 * @param mixed $plugin Value passed as the plugin being deactivated.
	 */
	public function test_unrelated_plugin_deactivation_keeps_migration_complete( $plugin ) {
		$this->set_plugin_file( $this->get_plugins_dir() . '/host-plugin/vendor/woocommerce/action-scheduler/action-scheduler.php' );

		ActionScheduler_DataController::mark_migration_incomplete( $plugin );

		$this->assertTrue( ActionScheduler_DataController::is_migration_complete() );
	}

	/**
	 * Plugins (and malformed hook arguments) that must not clear the flag.
	 *
	 * @return array
	 */
	public static function provide_unrelated_plugins() {
		return array(
			'plugin in its own directory'           => array( 'some-other-plugin/some-other-plugin.php' ),
			'single-file plugin'                    => array( 'hello.php' ),
			'directory sharing the name prefix'     => array( 'host-plugin-addon/host-plugin-addon.php' ),
			'plugin nested inside Action Scheduler' => array( 'host-plugin/vendor/woocommerce/action-scheduler/classes/fake-plugin.php' ),
			'empty string'                          => array( '' ),
			'non-string value'                      => array( array( 'host-plugin/host-plugin.php' ) ),
		);
	}

	/**
	 * Deactivating the plugin that provides the running copy of Action Scheduler clears the flag.
	 *
	 * @dataProvider provide_providing_plugins
	 *
	 * @param string $plugin_file Main file of the running copy of Action Scheduler, relative to the plugins directory.
	 * @param string $plugin      Plugin being deactivated, relative to the plugins directory.
	 */
	public function test_providing_plugin_deactivation_marks_migration_incomplete( $plugin_file, $plugin ) {
		$this->set_plugin_file( $this->get_plugins_dir() . '/' . $plugin_file );

		ActionScheduler_DataController::mark_migration_incomplete( $plugin );

		$this->assertFalse( ActionScheduler_DataController::is_migration_complete() );
	}

	/**
	 * Locations of the running copy of Action Scheduler, paired with the plugin providing it.
	 *
	 * @return array
	 */
	public static function provide_providing_plugins() {
		return array(
			'embedded in a host plugin'        => array(
				'host-plugin/vendor/woocommerce/action-scheduler/action-scheduler.php',
				'host-plugin/host-plugin.php',
			),
			'installed as a standalone plugin' => array(
				'action-scheduler/action-scheduler.php',
				'action-scheduler/action-scheduler.php',
			),
		);
	}

	/**
	 * The comparison is made against the real location of the running copy, as loaded by this test suite.
	 */
	public function test_deactivating_the_plugin_containing_the_loaded_copy_marks_migration_incomplete() {
		$this->set_plugin_file( $this->original_plugin_file );
		$plugin = basename( ActionScheduler::plugin_path( '' ) ) . '/action-scheduler.php';

		ActionScheduler_DataController::mark_migration_incomplete( $plugin );

		$this->assertFalse( ActionScheduler_DataController::is_migration_complete() );
	}

	/**
	 * A direct call without arguments keeps clearing the flag unconditionally.
	 */
	public function test_direct_call_without_plugin_marks_migration_incomplete() {
		ActionScheduler_DataController::mark_migration_incomplete();

		$this->assertFalse( ActionScheduler_DataController::is_migration_complete() );
	}

	/**
	 * The deactivate_plugin hook only clears the flag for the plugin providing the running copy.
	 */
	public function test_deactivate_plugin_hook_is_scoped_to_the_providing_plugin() {
		$this->set_plugin_file( $this->get_plugins_dir() . '/host-plugin/vendor/woocommerce/action-scheduler/action-scheduler.php' );
		ActionScheduler_DataController::init();

		do_action( 'deactivate_plugin', 'some-other-plugin/some-other-plugin.php', false ); // phpcs:ignore WooCommerce.Commenting.CommentHooks -- Invoke an existing core hook, not a new declaration.
		$this->assertTrue( ActionScheduler_DataController::is_migration_complete(), 'Deactivating an unrelated plugin keeps the migration complete.' );

		do_action( 'deactivate_plugin', 'host-plugin/host-plugin.php', false ); // phpcs:ignore WooCommerce.Commenting.CommentHooks -- Invoke an existing core hook, not a new declaration.
		$this->assertFalse( ActionScheduler_DataController::is_migration_complete(), 'Deactivating the plugin providing Action Scheduler marks the migration incomplete.' );
	}

	/**
	 * Get the plugins directory, resolved the same way as the paths it is compared with.
	 *
	 * @return string
	 */
	private function get_plugins_dir() {
		return untrailingslashit( wp_normalize_path( realpath( WP_PLUGIN_DIR ) ) );
	}

	/**
	 * Point the running copy of Action Scheduler at a different main plugin file, returning the previous one.
	 *
	 * @param string|null $plugin_file Main plugin file to set, or null to leave it unchanged.
	 *
	 * @return string The previous main plugin file.
	 */
	private function set_plugin_file( $plugin_file ) {
		$property = new ReflectionProperty( ActionScheduler::class, 'plugin_file' );
		$property->setAccessible( true );
		$previous = $property->getValue();

		if ( null !== $plugin_file ) {
			$property->setValue( null, $plugin_file );
		}

		return $previous;
	}
}
