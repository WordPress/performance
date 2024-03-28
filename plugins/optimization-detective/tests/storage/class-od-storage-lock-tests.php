<?php
/**
 * Tests for OD_Storage_Lock.
 *
 * @package optimization-detective
 *
 * @coversDefaultClass OD_Storage_Lock
 */

class OD_Storage_Lock_Tests extends WP_UnitTestCase {

	/**
	 * Tear down.
	 */
	public function tear_down() {
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		parent::tear_down();
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{set_up: Closure, expected: int}>
	 */
	public function data_provider_get_ttl(): array {
		return array(
			'unfiltered'        => array(
				'set_up'   => static function () {},
				'expected' => MINUTE_IN_SECONDS,
			),
			'filtered_hour'     => array(
				'set_up'   => static function () {
					add_filter(
						'od_url_metric_storage_lock_ttl',
						static function (): int {
							return HOUR_IN_SECONDS;
						}
					);
				},
				'expected' => HOUR_IN_SECONDS,
			),
			'filtered_negative' => array(
				'set_up'   => static function () {
					add_filter(
						'od_url_metric_storage_lock_ttl',
						static function (): int {
							return -100;
						}
					);
				},
				'expected' => 0,
			),
		);
	}

	/**
	 * Test get_ttl().
	 *
	 * @covers ::get_ttl
	 *
	 * @dataProvider data_provider_get_ttl
	 *
	 * @param Closure $set_up   Set up.
	 * @param int     $expected Expected value.
	 */
	public function test_get_ttl( Closure $set_up, int $expected ) {
		$set_up();
		$this->assertSame( $expected, OD_Storage_Lock::get_ttl() );
	}

	/**
	 * Test get_transient_key().
	 *
	 * @covers ::get_transient_key
	 */
	public function test_get_transient_key() {
		unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$first_key              = OD_Storage_Lock::get_transient_key();
		$this->assertStringStartsWith( 'url_metrics_storage_lock_', $first_key );

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.2';
		$second_key                      = OD_Storage_Lock::get_transient_key();
		$this->assertStringStartsWith( 'url_metrics_storage_lock_', $second_key );

		$this->assertNotEquals( $second_key, $first_key, 'Expected setting HTTP_X_FORWARDED_FOR header to take precedence over REMOTE_ADDR.' );
	}

	/**
	 * Test set_lock() and is_locked().
	 *
	 * @covers ::set_lock
	 * @covers ::is_locked
	 * @covers ::get_transient_key
	 * @covers ::get_ttl
	 */
	public function test_set_lock_and_is_locked() {
		$key = OD_Storage_Lock::get_transient_key();
		$ttl = OD_Storage_Lock::get_ttl();

		$transient_value      = null;
		$transient_expiration = null;
		add_action(
			"set_transient_{$key}",
			static function ( $filtered_value, $filtered_expiration ) use ( &$transient_value, &$transient_expiration ) {
				$transient_value      = $filtered_value;
				$transient_expiration = $filtered_expiration;
				return $filtered_value;
			},
			10,
			2
		);

		// Set the lock.
		OD_Storage_Lock::set_lock();
		$this->assertSame( $ttl, $transient_expiration );
		$this->assertLessThanOrEqual( microtime( true ), $transient_value );
		$this->assertEquals( $transient_value, get_transient( $key ) );
		$this->assertTrue( OD_Storage_Lock::is_locked() );

		// Simulate expired lock.
		set_transient( $key, microtime( true ) - HOUR_IN_SECONDS );
		$this->assertFalse( OD_Storage_Lock::is_locked() );

		// Clear the lock.
		add_filter( 'od_url_metric_storage_lock_ttl', '__return_zero' );
		OD_Storage_Lock::set_lock();
		$this->assertFalse( get_transient( $key ) );
		$this->assertFalse( OD_Storage_Lock::is_locked() );
	}
}
