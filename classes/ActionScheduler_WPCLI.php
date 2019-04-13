<?php

/**
 * Commands for the Action Scheduler by Prospress.
 */
class ActionScheduler_WPCLI extends WP_CLI_Command {

	/**
	 * Run the Action Scheduler
	 *
	 * ## OPTIONS
	 *
	 * [--batch-size=<size>]
	 * : The maximum number of actions to run. Defaults to 100.
	 *
	 * [--batches=<size>]
	 * : Limit execution to a number of batches. Defaults to 0, meaning batches will continue being executed until all actions are complete.
	 *
	 * [--cleanup-batch-size=<size>]
	 * : The maximum number of actions to clean up. Defaults to the value of --batch-size.
	 *
	 * [--hooks=<hooks>]
	 * : Only run actions with the specified hook. Omitting this option runs actions with any hook. Define multiple hooks as a comma separated string (without spaces), e.g. `--hooks=hook_one,hook_two,hook_three`
	 *
	 * [--group=<group>]
	 * : Only run actions from the specified group. Omitting this option runs actions from all groups.
	 *
	 * [--force]
	 * : Whether to force execution despite the maximum number of concurrent processes being exceeded.
	 *
	 * [--time]
	 * : Whether to print timestamp on each line.
	 *
	 * [--time-format=<format>]
	 * : Format for the timestamp. Defaults to Y-m-d H:i:s T.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Keyed arguments.
	 * @throws \WP_CLI\ExitException When an error occurs.
	 */
	public function run( $args, $assoc_args ) {
		$command = new ActionScheduler_WPCLI_Command_Run( $args, $assoc_args );
		$command->execute();
	}

	/**
	 * Get columns from associate arguments, and limit to available columns.
	 *
	 * @param mixed[] $assoc_args
	 * @param string[] $defaults
	 * @param string[] $available
	 * @param bool $include_stati
	 * @return string[]
	 */
	public static function get_columns( $assoc_args, $defaults = array(), $available = null, $include_stati = false ) {
		static $_columns = null;

		if ( ! is_null( $_columns ) ) {
			return $_columns;
		}

		# Get columns from passed associate arguments.
		$columns = \WP_CLI\Utils\get_flag_value( $assoc_args, 'columns', $defaults );

 		if ( ! is_array( $columns ) ) {
			$columns = explode( ',', $columns );
		}

		if ( is_array( $available ) ) {

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
			$columns = array_intersect( $columns, $available );

		}

		return ( $_columns = $columns );
	}

}
