<?php

/**
 * Class ActionScheduler_wcSystemStatus
 */
class ActionScheduler_wcSystemStatus {

	/**
	 * The active data stores
	 *
	 * @var ActionScheduler_Store
	 */
	protected $store;

	function __construct( $store ) {
		$this->store = $store;
	}

	/**
	 * Display action data, including number of actions grouped by status and the oldest & newest action in each status.
	 *
	 * Helpful to identify issues, like a clogged queue.
	 */
	public function print() {
		$action_counts     = $this->store->action_counts();
		$status_labels     = $this->store->get_status_labels();
		$oldest_and_newest = $this->store->action_dates();

		$this->get_template( $status_labels, $action_counts, $oldest_and_newest );
	}

	/**
	 * Get oldest or newest scheduled date for a given status.
	 *
	 * @param array $status_labels Set of statuses to find oldest & newest action for.
	 * @param array $action_counts Number of actions grouped by status.
	 * @param array $oldest_and_newest Date of the oldest and newest action with each status.
	 */
	protected function get_template( $status_labels, $action_counts, $oldest_and_newest ) {
		foreach ( $oldest_and_newest as $status => &$dates ) {
			foreach ( $dates as $point => &$value ) {
				if ( empty( $value ) ) {
					$value = '&ndash;';
				}
			}
		}
		?>

		<table class="wc_status_table widefat" cellspacing="0">
			<thead>
				<tr>
					<th colspan="5" data-export-label="Action Scheduler"><h2><?php esc_html_e( 'Action Scheduler', 'action-scheduler' ); ?><?php echo wc_help_tip( esc_html__( 'This section shows scheduled action counts.', 'action-scheduler' ) ); ?></h2></th>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Action Status', 'action-scheduler' ); ?></strong></td>
					<td class="help">&nbsp;</td>
					<td><strong><?php esc_html_e( 'Count', 'action-scheduler' ); ?></strong></td>
					<td><strong><?php esc_html_e( 'Oldest Scheduled Date', 'action-scheduler' ); ?></strong></td>
					<td><strong><?php esc_html_e( 'Newest Scheduled Date', 'action-scheduler' ); ?></strong></td>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $action_counts as $status => $count ) {
					// WC uses the 3rd column for export, so we need to display more data in that (hidden when viewed as part of the table) and add an empty 2nd column.
					printf(
						'<tr><td>%1$s</td><td>&nbsp;</td><td>%2$s<span style="display: none;">, Oldest: %3$s, Newest: %4$s</span></td><td>%3$s</td><td>%4$s</td></tr>',
						esc_html( $status_labels[ $status ] ),
						number_format_i18n( $count ),
						$oldest_and_newest[ $status ]['oldest'],
						$oldest_and_newest[ $status ]['newest']
					);
				}
				?>
			</tbody>
		</table>

		<?php
	}

}
