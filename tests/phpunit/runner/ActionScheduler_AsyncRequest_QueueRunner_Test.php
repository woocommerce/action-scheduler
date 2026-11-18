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
	 * Restore $_COOKIE, and remove the nonce some tests add to $_REQUEST.
	 */
	public function tear_down() {
		$_COOKIE = $this->original_cookies;
		unset( $_REQUEST['nonce'] );
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
	 * Handling a request still ends with wp_die() by default.
	 */
	public function test_maybe_handle_dies_by_default() {
		$runner = $this->get_runner_for_handled_request();

		$this->expectException( WPDieException::class );
		$runner->maybe_handle();
	}

	/**
	 * The bundled WP_Async_Request lets the wp_die filter return control after a request is handled, as newer copies
	 * of the library do (issue #1267). This relies on our copy of the class being the one loaded, as it is in the
	 * test suite.
	 */
	public function test_maybe_handle_returns_when_wp_die_is_filtered_off() {
		$runner = $this->get_runner_for_handled_request();
		$runs   = did_action( 'action_scheduler_run_queue' );

		add_filter( 'as_async_request_queue_runner_wp_die', '__return_false' );

		$this->assertNull( $runner->maybe_handle() );
		$this->assertSame( $runs + 1, did_action( 'action_scheduler_run_queue' ) );
	}

	/**
	 * Get a queue runner ready to handle an async request, without sleeping or dispatching another request.
	 *
	 * @return ActionScheduler_AsyncRequest_QueueRunner
	 */
	private function get_runner_for_handled_request() {
		add_filter( 'action_scheduler_async_request_sleep_seconds', '__return_zero' );
		add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );

		$_REQUEST['nonce'] = wp_create_nonce( 'as_async_request_queue_runner' );

		return new ActionScheduler_AsyncRequest_QueueRunner( ActionScheduler::store() );
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
