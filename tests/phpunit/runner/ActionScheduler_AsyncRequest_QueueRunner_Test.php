<?php

/**
 * Class ActionScheduler_AsyncRequest_QueueRunner_Test
 *
 * @group runners
 */
class ActionScheduler_AsyncRequest_QueueRunner_Test extends ActionScheduler_UnitTestCase {

	/**
	 * Cookies as they were before the test.
	 *
	 * @var array
	 */
	private $original_cookies;

	/**
	 * Back up $_COOKIE, which the tests change.
	 */
	public function set_up() {
		parent::set_up();
		$this->original_cookies = $_COOKIE;
	}

	/**
	 * Restore $_COOKIE.
	 */
	public function tear_down() {
		$_COOKIE = $this->original_cookies;
		parent::tear_down();
	}

	/**
	 * Cookies forwarded to the loopback request must be unslashed, or each chained
	 * request doubles the backslashes in values such as JSON (issue #1230).
	 */
	public function test_post_args_unslash_cookies() {
		$json = '{"first_name":"Jan"}';

		// WordPress adds slashes to $_COOKIE in wp_magic_quotes().
		$_COOKIE = array( 'json_cookie' => wp_slash( $json ) );

		$args = $this->get_post_args();

		$this->assertSame( $json, $args['cookies']['json_cookie'] );
	}

	/**
	 * Cookies set through the post args filter are passed on unchanged, and the
	 * remaining cookies are still unslashed.
	 */
	public function test_post_args_keep_filtered_cookies() {
		$json = '{"first_name":"Jan"}';

		$_COOKIE = array(
			'json_cookie'  => wp_slash( $json ),
			'large_cookie' => wp_slash( '{"step":"one"}' ),
		);
		add_filter(
			'as_async_request_queue_runner_post_args',
			function ( $args ) {
				unset( $args['cookies']['large_cookie'] );
				$args['cookies']['custom'] = 'a\\b';
				return $args;
			}
		);

		$args = $this->get_post_args();

		$this->assertSame(
			array(
				'json_cookie' => $json,
				'custom'      => 'a\\b',
			),
			$args['cookies']
		);
	}

	/**
	 * Call the protected get_post_args() method of a new queue runner.
	 *
	 * @return array
	 */
	private function get_post_args() {
		$runner = new ActionScheduler_AsyncRequest_QueueRunner( ActionScheduler::store() );
		$method = new ReflectionMethod( $runner, 'get_post_args' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		return $method->invoke( $runner );
	}
}
