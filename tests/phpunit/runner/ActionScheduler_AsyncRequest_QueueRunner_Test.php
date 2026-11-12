<?php

/**
 * Class ActionScheduler_AsyncRequest_QueueRunner_Test
 *
 * @group runners
 */
class ActionScheduler_AsyncRequest_QueueRunner_Test extends ActionScheduler_UnitTestCase {

	/**
	 * Cookies forwarded to the loopback request must be unslashed, or each chained
	 * request doubles the backslashes in values such as JSON (issue #1230).
	 */
	public function test_post_args_unslash_cookies() {
		$original_cookies = $_COOKIE;
		$json             = '{"first_name":"Jan"}';

		// WordPress adds slashes to $_COOKIE in wp_magic_quotes().
		$_COOKIE = array( 'json_cookie' => wp_slash( $json ) );

		$runner = new ActionScheduler_AsyncRequest_QueueRunner( ActionScheduler::store() );
		$method = new ReflectionMethod( $runner, 'get_post_args' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$args = $method->invoke( $runner );

		$_COOKIE = $original_cookies;

		$this->assertSame( $json, $args['cookies']['json_cookie'] );
	}
}
