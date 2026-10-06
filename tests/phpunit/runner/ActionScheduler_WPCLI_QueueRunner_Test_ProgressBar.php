<?php

/**
 * Progress bar test double.
 */
class ActionScheduler_WPCLI_QueueRunner_Test_ProgressBar {

	/**
	 * Current count.
	 *
	 * @var int
	 */
	private $current = 0;

	/**
	 * Advance the count.
	 */
	public function tick() {
		++$this->current;
	}

	/**
	 * Return the current count.
	 *
	 * @return int
	 */
	public function current() {
		return $this->current;
	}

	/**
	 * Finish the progress bar.
	 */
	public function finish() {}
}
