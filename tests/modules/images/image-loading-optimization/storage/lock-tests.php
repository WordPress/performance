<?php
/**
 * Tests for image-loading-optimization module storage/lock.php.
 *
 * @package performance-lab
 * @group   image-loading-optimization
 */

class Image_Loading_Optimization_Storage_Lock_Tests extends WP_UnitTestCase {

	/**
	 * Tear down.
	 */
	public function tear_down(){
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		parent::tear_down();
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{set_up: Closure, expected: int}>
	 */
	public function data_provider_ilo_get_url_metric_storage_lock_ttl(): array {
		return array(
			'unfiltered' => array(
				'set_up'   => static function () {},
				'expected' => MINUTE_IN_SECONDS,
			),
			'filtered_hour' => array(
				'set_up'   => static function () {
					add_filter(
						'ilo_url_metric_storage_lock_ttl',
						static function (): int {
							return HOUR_IN_SECONDS;
						}
					);
				},
				'expected' => HOUR_IN_SECONDS,
			),
			'filtered_negative' => array(
				'set_up' => static function () {
					add_filter(
						'ilo_url_metric_storage_lock_ttl',
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
	 * Test ilo_get_url_metric_storage_lock_ttl().
	 *
	 * @test
	 * @covers ::ilo_get_url_metric_storage_lock_ttl
	 * @dataProvider data_provider_ilo_get_url_metric_storage_lock_ttl
	 *
	 * @param Closure $set_up   Set up.
	 * @param int     $expected Expected value.
	 */
	public function test_ilo_get_url_metric_storage_lock_ttl( Closure $set_up, int $expected ) {
		$set_up();
		$this->assertSame( $expected, ilo_get_url_metric_storage_lock_ttl() );
	}

	/**
	 * Test ilo_get_url_metric_storage_lock_transient_key().
	 *
	 * @test
	 * @covers ::ilo_get_url_metric_storage_lock_transient_key
	 */
	public function test_ilo_get_url_metric_storage_lock_transient_key() {
		unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$first_key = ilo_get_url_metric_storage_lock_transient_key();
		$this->assertStringStartsWith( 'url_metrics_storage_lock_', $first_key );

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.2';
		$second_key = ilo_get_url_metric_storage_lock_transient_key();
		$this->assertStringStartsWith( 'url_metrics_storage_lock_', $second_key );

		$this->assertNotEquals( $second_key, $first_key, 'Expected setting HTTP_X_FORWARDED_FOR header to take precedence over REMOTE_ADDR.' );
	}

	/**
	 * Test ilo_set_url_metric_storage_lock() and ilo_is_url_metric_storage_locked().
	 *
	 * @test
	 * @covers ::ilo_set_url_metric_storage_lock
	 * @covers ::ilo_is_url_metric_storage_locked
	 */
	public function test_ilo_set_url_metric_storage_lock_and_ilo_is_url_metric_storage_locked() {
		$key = ilo_get_url_metric_storage_lock_transient_key();
		$ttl = ilo_get_url_metric_storage_lock_ttl();

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
		ilo_set_url_metric_storage_lock();
		$this->assertSame( $ttl, $transient_expiration );
		$this->assertLessThanOrEqual( microtime( true ), $transient_value );
		$this->assertEquals( $transient_value, get_transient( $key ) );
		$this->assertTrue( ilo_is_url_metric_storage_locked() );

		// Simulate expired lock.
		set_transient( $key, microtime( true ) - HOUR_IN_SECONDS );
		$this->assertFalse( ilo_is_url_metric_storage_locked() );

		// Clear the lock.
		add_filter( 'ilo_url_metric_storage_lock_ttl', '__return_zero' );
		ilo_set_url_metric_storage_lock();
		$this->assertFalse( get_transient( $key ) );
		$this->assertFalse( ilo_is_url_metric_storage_locked() );
	}
}
