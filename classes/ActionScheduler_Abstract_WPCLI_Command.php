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
	 * @var bool Enable printing of timestamp.
	 */
	protected $timestamp = false;

	/**
	 * @var string Format of timestamp.
	 */
	protected $timestamp_format = 'Y-m-d H:i:s T';

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
		$this->timestamp = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'time', false );
		$this->timestamp_format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'time-format', $this->timestamp_format );
	}

	/**
	 * Execute command.
	 */
	abstract public function execute();

	/**
	 * Wrapper for WP_CLI::log()
	 */
	protected function log( $message ) {
		WP_CLI::log( sprintf( '%s%s', $this->output_timestamp(), $message ) );
	}

	/**
	 * Wrapper for WP_CLI::warning()
	 */
	protected function warning( $message ) {
		WP_CLI::warning( sprintf( '%s%s', $this->output_timestamp(), $message ) );
	}

	/**
	 * Wrapper for WP_CLI::error()
	 */
	protected function error( $message ) {
		WP_CLI::error( sprintf( '%s%s', $this->output_timestamp(), $message ) );
	}

	/**
	 * Wrapper for WP_CLI::success()
	 */
	protected function success( $message ) {
		WP_CLI::success( sprintf( '%s%s', $this->output_timestamp(), $message ) );
	}

	/**
	 * Print timestamp to CLI, if enabled.
	 *
	 * @return string
	 */
	protected function output_timestamp() {
		if ( empty( $this->timestamp ) ) {
			return '';
		}

		return '[' . as_get_datetime_object()->format( $this->timestamp_format ) . '] ';
	}

	/**
	 * Wrapper for WP_CLI_Utils\format_items( 'table' )
	 */
	protected function table( $items, $columns ) {
		\WP_CLI\Utils\format_items( 'table', $items, $columns );
 		if ( ! empty( $this->timestamp ) ) {
			$this->log( sprintf( 'Table generated.', $this->output_timestamp() ) );
		}
	}

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
			$available = array_merge( $available, array_keys( $this->store->get_status_labels() ) );
		}

		# Identify requested columns that are not available.
		$unavailable = array_diff( $columns, $available );

		# Print warning about unavailable columns.
		foreach ( $unavailable as $column ) {
			$this->warning( 'Column \'' . $column . '\' is not available.' );
		}

		# Set columns that are available.
		$this->columns = array_intersect( $columns, $available );
		return $this->columns;
	}

}
