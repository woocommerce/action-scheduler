<?php

/**
 * Class ActionScheduler_DateTime_Test
 *
 * @group datetime
 * @group helpers
 */
class ActionScheduler_DateTime_Test extends ActionScheduler_UnitTestCase {

	/**
	 * Test default state of ActionScheduler_DateTime with explicit UTC timezone.
	 */
	public function test_default_utc_datetime() {
		$utc      = new DateTimeZone( 'UTC' );
		$datetime = new ActionScheduler_DateTime( 'now', $utc );

		$this->assertSame( 0, $datetime->getOffset() );
		$this->assertIsInt( $datetime->getTimestamp() );
		$this->assertSame( $datetime->getTimestamp(), $datetime->getOffsetTimestamp() );
	}

	/**
	 * Test setUtcOffset and getOffset with non-zero offsets.
	 *
	 * When a non-zero offset is provided, getOffset() must return that custom offset
	 * regardless of the system/PHP default timezone.
	 *
	 * @dataProvider non_zero_offset_provider
	 *
	 * @param int|string $offset   The offset to set.
	 * @param int        $expected Expected integer offset.
	 */
	public function test_set_and_get_utc_offset( $offset, $expected ) {
		$datetime = new ActionScheduler_DateTime();
		$datetime->setUtcOffset( $offset );

		$this->assertSame( $expected, $datetime->getOffset() );
		$this->assertSame( $datetime->getTimestamp() + $expected, $datetime->getOffsetTimestamp() );
	}

	/**
	 * Data provider for non-zero offsets.
	 *
	 * @return array[]
	 */
	public function non_zero_offset_provider() {
		return array(
			'positive int offset'    => array( 3600, 3600 ),
			'large positive offset'  => array( 19800, 19800 ),
			'negative int offset'    => array( -18000, -18000 ),
			'positive string offset' => array( '7200', 7200 ),
			'negative string offset' => array( '-14400', -14400 ),
		);
	}

	/**
	 * Test that when utcOffset is zero or not set, getOffset delegates to parent DateTime offset.
	 */
	public function test_zero_offset_delegates_to_parent() {
		$datetime = new ActionScheduler_DateTime();
		$parent   = new DateTime();

		$this->assertSame( $parent->getOffset(), $datetime->getOffset() );

		// Explicitly setting offset to 0 should still fall back to parent::getOffset().
		$datetime->setUtcOffset( 0 );
		$this->assertSame( $parent->getOffset(), $datetime->getOffset() );
	}

	/**
	 * Test setTimezone resets utcOffset to zero and applies the new timezone.
	 *
	 * @dataProvider timezone_provider
	 *
	 * @param string $timezone_string Valid timezone name.
	 */
	public function test_set_timezone_resets_utc_offset( $timezone_string ) {
		$datetime = new ActionScheduler_DateTime();
		$datetime->setUtcOffset( 7200 );
		$this->assertSame( 7200, $datetime->getOffset() );

		$tz     = new DateTimeZone( $timezone_string );
		$result = $datetime->setTimezone( $tz );

		$this->assertSame( $datetime, $result );
		$this->assertSame( $timezone_string, $datetime->getTimezone()->getName() );

		// Once a timezone is set, getOffset delegates to parent::getOffset() matching the timezone.
		$expected_offset = $tz->getOffset( $datetime );
		$this->assertSame( $expected_offset, $datetime->getOffset() );
		$this->assertSame( $datetime->getTimestamp() + $expected_offset, $datetime->getOffsetTimestamp() );
	}

	/**
	 * Data provider for test_set_timezone_resets_utc_offset.
	 *
	 * @return array[]
	 */
	public function timezone_provider() {
		return array(
			array( 'America/New_York' ),
			array( 'Europe/London' ),
			array( 'Asia/Tokyo' ),
			array( 'Australia/Sydney' ),
			array( 'UTC' ),
		);
	}

	/**
	 * Test getTimestamp compatibility and consistency.
	 */
	public function test_get_timestamp_consistency() {
		$time     = time();
		$datetime = new ActionScheduler_DateTime( '@' . $time );

		$this->assertSame( $time, $datetime->getTimestamp() );
		$this->assertSame( (int) $datetime->format( 'U' ), $datetime->getTimestamp() );
	}
}
