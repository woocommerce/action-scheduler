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

		$this->assertStringContainsString( '&lt;script&gt;test&lt;/script&gt;', $output );
		$this->assertStringContainsString( 'ID: 123', $output );
		$this->assertStringNotContainsString( '<script>', $output );
	}
}
