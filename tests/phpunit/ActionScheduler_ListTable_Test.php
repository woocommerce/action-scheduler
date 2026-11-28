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

	public function test_column_args_displays_short_args_in_full() {
		$reflection = new ReflectionClass( ActionScheduler_ListTable::class );
		$list_table = $reflection->newInstanceWithoutConstructor();
		$output     = $list_table->column_args( array( 'args' => array( 'order_id' => 123 ) ) );

		$this->assertSame( '<ul><li><code>&#039;order_id&#039; => 123</code></li></ul>', $output, 'Short args are displayed in full, without being collapsed' );
	}

	public function test_column_args_collapses_long_args() {
		$reflection = new ReflectionClass( ActionScheduler_ListTable::class );
		$list_table = $reflection->newInstanceWithoutConstructor();
		$payload    = str_repeat( 'x', 7900 );
		$output     = $list_table->column_args( array( 'args' => array( 'payload' => $payload ) ) );

		$this->assertStringStartsWith( '<details><summary>Arguments (7,915 characters)</summary><ul>', $output, 'Long args are collapsed behind a summary that states their length' );
		$this->assertStringEndsWith( '</ul></details>', $output );
		$this->assertStringContainsString( "<li><code>&#039;payload&#039; => &#039;{$payload}&#039;</code></li>", $output, 'The full args remain available once expanded' );
	}

	public function test_column_args_measures_length_of_displayed_text() {
		$reflection = new ReflectionClass( ActionScheduler_ListTable::class );
		$list_table = $reflection->newInstanceWithoutConstructor();

		// Escaping inflates the markup (each '&' becomes '&amp;') well past the limit, but not the text that is displayed.
		$output = $list_table->column_args( array( 'args' => array( str_repeat( '&', 400 ) ) ) );
		$this->assertStringStartsWith( '<ul>', $output, 'Markup and HTML entities do not count towards the length of the args' );

		$output = $list_table->column_args( array( 'args' => array( str_repeat( '&', 600 ) ) ) );
		$this->assertStringStartsWith( '<details><summary>Arguments (607 characters)</summary>', $output );
	}

	public function test_column_args_filter_receives_uncollapsed_markup() {
		$reflection = new ReflectionClass( ActionScheduler_ListTable::class );
		$list_table = $reflection->newInstanceWithoutConstructor();
		$payload    = str_repeat( 'x', 1000 );
		$filtered   = null;
		$callback   = function ( $row_html ) use ( &$filtered ) {
			$filtered = $row_html;
			return $row_html;
		};

		add_filter( 'action_scheduler_list_table_column_args', $callback );
		$output = $list_table->column_args( array( 'args' => array( 'payload' => $payload ) ) );
		remove_filter( 'action_scheduler_list_table_column_args', $callback );

		$this->assertSame( "<ul><li><code>&#039;payload&#039; => &#039;{$payload}&#039;</code></li></ul>", $filtered, 'Filter callbacks receive the same markup as before' );
		$this->assertSame( "<details><summary>Arguments (1,015 characters)</summary>{$filtered}</details>", $output );
	}

	public function test_column_args_collapses_long_filtered_markup() {
		$reflection = new ReflectionClass( ActionScheduler_ListTable::class );
		$list_table = $reflection->newInstanceWithoutConstructor();
		$custom     = '<p>' . str_repeat( 'y', 501 ) . '</p>';
		$callback   = function () use ( $custom ) {
			return $custom;
		};

		add_filter( 'action_scheduler_list_table_column_args', $callback );
		$output_without_args = $list_table->column_args( array( 'args' => array() ) );
		$output_with_args    = $list_table->column_args( array( 'args' => array( 'order_id' => 123 ) ) );
		remove_filter( 'action_scheduler_list_table_column_args', $callback );

		$expected = "<details><summary>Arguments (501 characters)</summary>{$custom}</details>";
		$this->assertSame( $expected, $output_without_args, 'Long markup supplied by a filter callback is collapsed, even for actions without args' );
		$this->assertSame( $expected, $output_with_args, 'Long markup supplied by a filter callback is collapsed' );
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
