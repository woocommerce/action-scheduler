<?php

/**
 * Action Scheduler abstract class for WP CLI commands.
 */
abstract class ActionScheduler_Abstract_WPCLI_Command {

	/**
	 * @var array
	 */
	protected $args;
	protected $assoc_args;

	/**
	 * @var string[] $columns
	 */
	protected $columns = array();

	/**
	 * Construct.
	 */
	public function __construct( $args, $assoc_args ) {
		$this->args = $args;
		$this->assoc_args = $assoc_args;
	}

	/**
	 * Execute command.
	 */
	abstract public function execute();

	/**
	 * Get columns from associate arguments, and limit to available columns.
	 *
	 * @param string[] $defaults
	 * @param string[] $available
	 * @param bool $include_stati
	 * @return string[]
	 */
	protected function get_columns( $defaults = array(), $available = array(), $include_stati = false ) {
		if ( ! empty( $this->columns ) ) {
			return $this->columns;
		}

		# Get columns from passed associate arguments.
		$columns = \WP_CLI\Utils\get_flag_value( $this->assoc_args, 'columns', $defaults );

		if ( ! is_array( $columns ) ) {
			$columns = explode( ',', $columns );
		}

		# Include action stati in available columns.
		if ( false !== $include_stati ) {
			$store = ActionScheduler::store();
			$available = array_merge( $available, array_keys( $store->get_status_labels() ) );
		}

		# Identify requested columns that are not available.
		$unavailable = array_diff( $columns, $available );

		# Print warning about unavailable columns.
		foreach ( $unavailable as $column ) {
			\WP_CLI::warning( 'Column \'' . $column . '\' is not available.' );
		}

		# Set columns that are available.
		$this->columns = array_intersect( $columns, $available );
		return $this->columns;
	}

}
