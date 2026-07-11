<?php

/**
 * A placeholder schedule for a stored action whose real schedule could not be safely deserialized
 * because it references one or more classes Action Scheduler does not recognize.
 *
 * Such a schedule is structurally valid (its outermost object implements ActionScheduler_Schedule)
 * but nests a class outside the trusted allow-list — for example a support class shipped by an
 * extension that is not currently loaded or allow-listed. It is not, in itself, evidently dangerous,
 * so rather than silently cancelling the action (which loses it), we substitute this placeholder.
 * That keeps the action visible so a site operator can review it, move it to a failed state, and — if
 * appropriate — force it to run. @see https://github.com/woocommerce/action-scheduler/issues/1318
 *
 * It extends ActionScheduler_NullSchedule, so it carries no runnable date and is treated as a one-off
 * (non-recurring) for scheduling purposes.
 *
 * @since 4.1.0
 */
class ActionScheduler_UnrecognizedSchedule extends ActionScheduler_NullSchedule {

	/**
	 * The class names discovered in the stored blob that Action Scheduler did not recognize.
	 *
	 * @var string[]
	 */
	protected $unrecognized_classes = array();

	/**
	 * @param string[] $unrecognized_classes Class names from the blob that could not be vetted.
	 */
	public function __construct( array $unrecognized_classes = array() ) {
		parent::__construct();
		$this->unrecognized_classes = array_values( array_unique( $unrecognized_classes ) );
	}

	/**
	 * The unrecognized class names, for display and logging.
	 *
	 * @return string[]
	 */
	public function get_unrecognized_classes() {
		return $this->unrecognized_classes;
	}
}
