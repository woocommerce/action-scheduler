<?php

/**
 * Class ActionScheduler_Action_Test
 * @group actions
 */
class ActionScheduler_Action_Test extends ActionScheduler_UnitTestCase {
	public function test_set_schedule() {
		$time     = as_get_datetime_object();
		$schedule = new ActionScheduler_SimpleSchedule( $time );
		$action   = new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array(), $schedule );
		$this->assertEquals( $schedule, $action->get_schedule() );
	}

	public function test_null_schedule() {
		$action = new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK );
		$this->assertInstanceOf( 'ActionScheduler_NullSchedule', $action->get_schedule() );
	}

	public function test_set_hook() {
		$action = new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK );
		$this->assertEquals( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, $action->get_hook() );
	}

	public function test_args() {
		$action = new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK );
		$this->assertEmpty( $action->get_args() );

		$action = new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array( 5, 10, 15 ) );
		$this->assertEqualSets( array( 5, 10, 15 ), $action->get_args() );
	}

	public function test_set_group() {
		$action = new ActionScheduler_Action( ActionScheduler_Callbacks::HOOK_WITH_CALLBACK, array(), null, 'my_group' );
		$this->assertEquals( 'my_group', $action->get_group() );
	}

	public function test_execute() {
		$mock = new MockAction();

		$random = md5( wp_rand() );
		add_action( $random, array( $mock, 'action' ) );

		$action = new ActionScheduler_Action( $random, array( $random ) );
		$action->execute();

		remove_action( $random, array( $mock, 'action' ) );

		$this->assertEquals( 1, $mock->get_call_count() );
		$events = $mock->get_events();
		$event  = reset( $events );
		$this->assertEquals( $random, reset( $event['args'] ) );
	}
}
