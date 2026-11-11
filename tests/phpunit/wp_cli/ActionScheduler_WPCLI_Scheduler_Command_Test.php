<?php

/**
 * @group wp-cli
 */
class ActionScheduler_WPCLI_Scheduler_Command_Test extends ActionScheduler_UnitTestCase {

	/**
	 * WP-CLI is not loaded by the test suite, so supply its base command class from the dev dependency.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		if ( ! class_exists( 'WP_CLI_Command', false ) ) {
			require_once dirname( __DIR__, 3 ) . '/vendor/wp-cli/wp-cli/php/class-wp-cli-command.php';
		}
	}

	/**
	 * @dataProvider comma_separated_string_provider
	 *
	 * @param string $input    Raw option value.
	 * @param array  $expected Parsed values.
	 */
	public function test_parse_comma_separated_string( $input, $expected ) {
		$method = new ReflectionMethod( ActionScheduler_WPCLI_Scheduler_command::class, 'parse_comma_separated_string' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$deprecations = array();
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler(
			function ( $errno, $errstr ) use ( &$deprecations ) {
				$deprecations[] = $errstr;
				return true;
			},
			E_DEPRECATED
		);

		try {
			$result = $method->invoke( new ActionScheduler_WPCLI_Scheduler_command(), $input );
		} finally {
			restore_error_handler();
		}

		$this->assertSame( array(), $deprecations );
		$this->assertSame( $expected, array_values( $result ) );
	}

	/**
	 * @return array[]
	 */
	public function comma_separated_string_provider() {
		return array(
			'empty (the --exclude-groups default)' => array( '', array() ),
			'single value'                         => array( 'group-a', array( 'group-a' ) ),
			'multiple values'                      => array( 'group-a,group-b', array( 'group-a', 'group-b' ) ),
			'quoted value containing a comma'      => array( '"group,a",group-b', array( 'group,a', 'group-b' ) ),
		);
	}

	/**
	 * @dataProvider force_batch_provider
	 *
	 * @param int      $claims      Number of claims in progress.
	 * @param bool     $force       Value of the --force flag.
	 * @param int|null $force_up_to Value of the --force-up-to option.
	 * @param bool     $expected    Whether the batch should be forced.
	 */
	public function test_should_force_batch( $claims, $force, $force_up_to, $expected ) {
		$store = ActionScheduler::store();

		for ( $i = 0; $i < $claims; $i++ ) {
			as_schedule_single_action( time() - 1, __METHOD__ );
			$store->stake_claim( 1 );
		}

		$this->assertSame( $claims, $store->get_claim_count() );

		$method = new ReflectionMethod( ActionScheduler_WPCLI_Scheduler_command::class, 'should_force_batch' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$this->assertSame( $expected, $method->invoke( new ActionScheduler_WPCLI_Scheduler_command(), $force, $force_up_to ) );
	}

	/**
	 * @return array[]
	 */
	public function force_batch_provider() {
		return array(
			'neither option'                              => array( 2, false, null, false ),
			'--force'                                     => array( 2, true, null, true ),
			'--force-up-to above the claim count'         => array( 2, false, 3, true ),
			'--force-up-to equal to the claim count'      => array( 2, false, 2, false ),
			'--force-up-to below the claim count'         => array( 2, false, 1, false ),
			'--force-up-to takes precedence over --force' => array( 2, true, 2, false ),
		);
	}
}
