<?php
/**
 * ActionScheduler_AsyncRequest_QueueRunner
 */

defined( 'ABSPATH' ) || exit;

/**
 * ActionScheduler_AsyncRequest_QueueRunner class.
 */
class ActionScheduler_AsyncRequest_QueueRunner extends WP_Async_Request {

	/**
	 * Prefix for ajax hooks
	 *
	 * @var string
	 * @access protected
	 */
	protected $prefix = 'as';

	/**
	 * Action for ajax hooks
	 *
	 * @var string
	 * @access protected
	 */
	protected $action = 'async_request_queue_runner';

	/**
	 * Handle async requests
	 *
	 * Run a queue, and if dispatch another async request to run another queue
	 * if there are still pending actions after completing a queue in this request.
	 */
	protected function handle() {
		ActionScheduler_QueueRunner::instance()->run();
		ActionScheduler_QueueRunner::instance()->maybe_dispatch_async_runner();
	}
}
