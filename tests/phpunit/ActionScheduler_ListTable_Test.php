<?php

/**
 * Tests for the Action Scheduler admin list table.
 */
class ActionScheduler_ListTable_Test extends ActionScheduler_UnitTestCase {

	public function test_column_hook_displays_escaped_hook_and_action_id() {
		$reflection = new ReflectionClass( ActionScheduler_ListTable::class );
		$list_table = $reflection->newInstanceWithoutConstructor();
		$output     = $list_table->column_hook(
			array(
				'ID'          => 123,
				'hook'        => '<script>test</script>',
				'status_name' => ActionScheduler_Store::STATUS_COMPLETE,
			)
		);

		$this->assertStringContainsString( '&lt;script&gt;test&lt;/script&gt;', $output, 'HTML elements will be escaped' );
		$this->assertStringContainsString( 'ID: 123', $output );
		$this->assertStringNotContainsString( '<script>', $output );
	}

	public function test_row_action_notice_escapes_hook() {
		$action_id = as_schedule_single_action( time() + HOUR_IN_SECONDS, '<em>test</em>' );
		set_transient(
			'action_scheduler_admin_notice',
			array(
				'action_id'       => $action_id,
				'success'         => 1,
				'error_message'   => '',
				'row_action_type' => 'run',
			),
			30
		);

		$reflection = new ReflectionClass( ActionScheduler_ListTable::class );
		$list_table = $reflection->newInstanceWithoutConstructor();
		$properties = array(
			'store'  => ActionScheduler::store(),
			'runner' => ActionScheduler::runner(),
		);
		foreach ( $properties as $name => $value ) {
			$property = $reflection->getProperty( $name );
			$property->setAccessible( true );
			$property->setValue( $list_table, $value );
		}

		ob_start();
		$list_table->display_admin_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString( '&lt;em&gt;test&lt;/em&gt;', $output, 'The hook name will be escaped' );
		$this->assertStringNotContainsString( '<em>', $output );
	}
}
