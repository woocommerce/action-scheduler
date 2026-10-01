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
}
