<?php

/**
 * Provides an increasing time delay.
 */
class ActionScheduler_Pipeline_LoopDelay {
	const DELAY_STAYS_CONSTANT     = 0;
	const DELAY_DOUBLES            = 1;
	const DELAY_INCREASES_LINEARLY = 2;

	/**
	 * @var int
	 */
	private $increment_block;

	/**
	 * @var int
	 */
	private $max_delay;

	/**
	 * @var int
	 */
	private $change_calculation;

	/**
	 * @var int
	 */
	private $next_delay = 0;

	/**
	 * @param int $change_calculation
	 * @param int $increment_block
	 * @param int $max_delay
	 *
	 *@throws ActionScheduler_Pipeline_Exception
	 *
	 */
	public function __construct( int $change_calculation = self::DELAY_DOUBLES, int $increment_block = 50000, int $max_delay = 1000000 ) {
		if ( $change_calculation < 0 || $change_calculation > 2 ) {
			throw new ActionScheduler_Pipeline_Exception(
				__( 'Invalid loop delay method.', 'action-scheduler' ),
				ActionScheduler_Pipeline_Exception::BAD_LOOP_DELAY_METHOD
			);
		}

		$this->increment_block    = $increment_block;
		$this->max_delay          = $max_delay;
		$this->change_calculation = $change_calculation;

		if ( $this->change_calculation === self::DELAY_STAYS_CONSTANT ) {
			$this->next_delay = $increment_block;
		}
	}

	/**
	 * @return void
	 */
	public function wait() {
		$delay   = $this->get_next_delay();
		print $delay . ' ' ;
		$seconds = (int) floor( $delay / 1000000 );

		// We can't reliably use utime() to wait for more than 1s.
		if ( $seconds ) {
			sleep( $seconds );
		}

		$remaining_micro_seconds = $delay - ( $seconds * 1000000 );

		if ( $remaining_micro_seconds > 0 ) {
			usleep( $remaining_micro_seconds );
		}
	}

	/**
	 * @param int $start_at = 0
	 *
	 * @return $this
	 */
	public function reset( int $start_at = 0 ): ActionScheduler_Pipeline_LoopDelay {
		for ( $i = 0; $i <= $start_at; $i++ ) {
			$this->get_next_delay();
		}

		return $this;
	}

	/**
	 * @return int
	 */
	private function get_next_delay(): int {
		$next_delay = $this->next_delay;
		$this->update_next_delay();
		return $next_delay;
	}

	/**
	 * @return void
	 */
	private function update_next_delay() {
		switch ( $this->change_calculation ) {
			case self::DELAY_STAYS_CONSTANT:
				break;

			case self::DELAY_DOUBLES:
				$this->next_delay = $this->next_delay > 0 ? $this->next_delay * 2 : $this->increment_block;
				break;

			case self::DELAY_INCREASES_LINEARLY:
				$this->next_delay += $this->increment_block;
				break;
		}

		$this->next_delay = min( $this->next_delay, $this->max_delay );
	}
}
