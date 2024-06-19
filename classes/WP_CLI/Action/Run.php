<?php declare( strict_types=1 );

class ActionScheduler_WPCLI_Action_Run_Command extends ActionScheduler_WPCLI_Command {

	protected $action_ids = array();
	protected $action_counts = array(
		'executed' => 0,
		'failed'   => 0,
		'ignored'  => 0,
		'invalid'  => 0,
		'total'    => 0,
	);

	function __construct( array $args, array $assoc_args ) {
		parent::__construct( $args, $assoc_args );

		$this->action_ids = array_map( 'absint', $args );
		$this->action_counts['total'] = count( $this->action_ids );

		add_action( 'action_scheduler_execution_ignored', array( $this, 'action__ignored'  ) );
		add_action( 'action_scheduler_after_execute',     array( $this, 'action__executed' ) );
		add_action( 'action_scheduler_failed_execution',  array( $this, 'action__failed'   ), 10, 2 );
		add_action( 'action_scheduler_failed_validation', array( $this, 'action__invalid'  ), 10, 2 );
	}

	/**
	 * Execute.
	 *
	 * @uses \ActionScheduler_Abstract_QueueRunner::process_action()
	 * @uses \WP_CLI::warning()
	 * @uses \WP_CLI::success()
	 * @return void
	 */
	function execute() : void {
		$runner = \ActionScheduler::runner();

		$progress_bar = \WP_CLI\Utils\make_progress_bar(
			sprintf(
				_n( 'Executing %d action', 'Executing %d actions', $this->action_counts['total'], 'action-scheduler' ),
				number_format_i18n( $this->action_counts['total'] )
			),
			$this->action_counts['total']
		);

		foreach ( $this->action_ids as $action_id ) {
			$runner->process_action( $action_id, 'Action Scheduler CLI' );
			$progress_bar->tick();
		}

		$progress_bar->finish();

		foreach ( array(
			'ignored',
			'invalid',
			'failed',
		) as $type ) {
			$count = $this->action_counts[ $type ];

			if ( empty( $count ) )
				continue;

			\WP_CLI::warning( sprintf(
				_n( '%d action %s.', '%d actions %s.', $count, 'action-scheduler' ),
				number_format_i18n( $count ),
				$type
			) );
		}

		\WP_CLI::success( sprintf(
			_n( 'Executed %d action.', 'Executed %d actions.', $this->action_counts['executed'], 'action-scheduler' ),
			number_format_i18n( $this->action_counts['executed'] )
		) );
	}

	/**
	 * Action: action_scheduler_execution_ignored
	 *
	 * @param int $action_id
	 * @uses \WP_CLI::debug()
	 * @return void
	 */
	function action__ignored( int $action_id ) : void {
		if ( !in_array( $action_id, $this->action_ids ) )
			return;

		$this->action_counts['ignored']++;
		\WP_CLI::debug( sprintf( 'Action %d was ignored.', $action_id ) );
	}

	/**
	 * Action: action_scheduler_after_execute
	 *
	 * @param int $action_id
	 * @uses \WP_CLI::success()
	 * @return void
	 */
	function action__executed( int $action_id ) : void {
		if ( !in_array( $action_id, $this->action_ids ) )
			return;

		$this->action_counts['executed']++;
		\WP_CLI::debug( sprintf( 'Action %d was executed.', $action_id ) );
	}

	/**
	 * Action: action_scheduler_failed_execution
	 *
	 * @param int $action_id
	 * @param \Exception $e
	 * @uses \WP_CLI::debug()
	 * @return void
	 */
	function action__failed( int $action_id, \Exception $e ) : void {
		if ( !in_array( $action_id, $this->action_ids ) )
			return;

		$this->action_counts['failed']++;
		\WP_CLI::debug( sprintf( 'Action %d failed execution: %s', $action_id, $e->getMessage() ) );
	}

	/**
	 * Action: action_scheduler_failed_validation
	 *
	 * @param int $action_id
	 * @param \Exception $e
	 * @uses \WP_CLI::debug()
	 * @return void
	 */
	function action__invalid( int $action_id, \Exception $e ) : void {
		if ( !in_array( $action_id, $this->action_ids ) )
			return;

		$this->action_counts['invalid']++;
		\WP_CLI::debug( sprintf( 'Action %d failed validation: %s', $action_id, $e->getMessage() ) );
	}

}
