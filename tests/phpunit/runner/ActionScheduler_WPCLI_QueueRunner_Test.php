<?php

require_once __DIR__ . '/ActionScheduler_WPCLI_QueueRunner_Test_Claim.php';
require_once __DIR__ . '/ActionScheduler_WPCLI_QueueRunner_Test_ProgressBar.php';
require_once __DIR__ . '/ActionScheduler_WPCLI_QueueRunner_Test_Store.php';
require_once __DIR__ . '/ActionScheduler_WPCLI_QueueRunner_Testable.php';

/**
 * Tests for the WP-CLI queue runner lifecycle.
 */
class ActionScheduler_WPCLI_QueueRunner_Test extends PHPUnit\Framework\TestCase {

	/**
	 * Queue maintenance is throttled and hooks are initialized only once.
	 */
	public function test_initializes_only_once() {
		$runner = new ActionScheduler_WPCLI_QueueRunner_Testable( array() );

		$runner->prepare_for_test();
		$runner->prepare_for_test();

		$this->assertSame( 1, $runner->cleanup_count );
		$this->assertSame( 1, $runner->add_hooks_count );
	}

	/**
	 * The stop predicate is checked between actions and the claim is released.
	 */
	public function test_stops_between_actions() {
		$runner = new ActionScheduler_WPCLI_QueueRunner_Testable( array( 10, 20, 30 ) );

		$processed = $runner->run(
			'WP CLI Test',
			static function ( $batch_actions_processed ) {
				return $batch_actions_processed >= 1;
			}
		);

		$this->assertSame( 1, $processed );
		$this->assertSame( array( 10 ), $runner->processed_actions );
		$this->assertTrue( $runner->store->claim_released );
	}
}
