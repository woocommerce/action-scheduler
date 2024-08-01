<?php

/**
 * Class ActionScheduler_NullLogEntry
 */
class ActionScheduler_NullLogEntry extends ActionScheduler_LogEntry {
	/**
	 * Constructor
	 *
	 * @param mixed  $action_id Action ID.
	 * @param string $message   Message.
	 */
	public function __construct( $action_id = '', $message = '' ) {
		// nothing to see here.
	}
}
