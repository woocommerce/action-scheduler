<?php
/**
 *
 */

/**
 * @group stores
 */
class ActionScheduler_StoreTest extends ActionScheduler_UnitTestCase {

	protected static $ignore_files = true;

	/** @var ActionScheduler_Store */
	protected $store = null;

	function setUp() {
		parent::setUp();
		$this->store = $this->getMockForAbstractClass( 'ActionScheduler_Store' );
	}

	/**
	 * @dataProvider valid_fields_provider
	 */
	public function test_get_valid_fields( $fields, $expected ) {
		$get_valid_fields = $this->get_accessible_protected_method( $this->store, 'get_valid_fields' );
		$result           = $get_valid_fields->invoke( $this->store, $fields );
		$this->assertEquals( $expected, $result );
	}

	public function valid_fields_provider() {
		return array(
			// Valid fields - we expect to get them all back.
			array(
				array(
					'action_id' => '1',
					'hook'      => 'my_hook',
					'group'     => 'my_group',
				),
				array(
					'action_id' => '1',
					'hook'      => 'my_hook',
					'group'     => 'my_group',
				),
			),

			// Invalid fields - we expect to get none of them back.
			array(
				array(
					'foo' => 'bar',
					'bar' => 'baz',
					'baz' => 'boo',
				),
				array(),
			),

			// Mix of fields - we expect some back and some removed.
			array(
				array(
					'action_id' => '1',
					'hook'      => 'my_hook',
					'group'     => 'my_group',
					'foo'       => 'bar',
					'bar'       => 'baz',
					'baz'       => 'boo',
				),
				array(
					'action_id' => '1',
					'hook'      => 'my_hook',
					'group'     => 'my_group',
				),
			),
		);
	}
}
