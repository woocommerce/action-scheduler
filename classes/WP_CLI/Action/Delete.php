<?php declare( strict_types=1 );

class ActionScheduler_WPCLI_Action_Delete_Command extends ActionScheduler_WPCLI_Command {

	protected $action_ids = array();
	protected $action_counts = array(
		'deleted' => 0,
		'total'   => 0,
	);

	function __construct( array $args, array $assoc_args ) {
		parent::__construct( $args, $assoc_args );

		$this->action_ids = array_map( 'absint', $args );
		$this->action_counts['total'] = count( $this->action_ids );

		add_action( 'action_scheduler_deleted_action', array( $this, 'action__deleted' ) );
	}

	/**
	 * Execute.
	 *
	 * @uses \ActionScheduler_Store::delete_action()
	 * @uses \WP_CLI::warning()
	 * @uses \WP_CLI::success()
	 * @return void
	 */
	function execute() : void {
		$store = \ActionScheduler::store();

		$progress_bar = \WP_CLI\Utils\make_progress_bar(
			sprintf(
				_n( 'Deleting %d action', 'Deleting %d actions', $this->action_counts['total'], 'action-scheduler' ),
				number_format_i18n( $this->action_counts['total'] )
			),
			$this->action_counts['total']
		);

		foreach ( $this->action_ids as $action_id ) {
			$store->delete_action( $action_id );
			$progress_bar->tick();
		}

		$progress_bar->finish();

		\WP_CLI::success( sprintf(
			_n( 'Deleted %d action.', 'Deleted %d actions.', $this->action_counts['deleted'], 'action-scheduler' ),
			number_format_i18n( $this->action_counts['deleted'] )
		) );
	}

	/**
	 * Action: action_scheduler_deleted_action
	 *
	 * @param int $action_id
	 * @uses \WP_CLI::debug()
	 * @return void
	 */
	function action__deleted( int $action_id ) : void {
		if ( !in_array( $action_id, $this->action_ids ) )
			return;

		$this->action_counts['deleted']++;
		\WP_CLI::debug( sprintf( 'Action %d was deleted.', $action_id ) );
	}

}
