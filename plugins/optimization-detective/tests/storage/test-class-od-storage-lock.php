<?php
/**
 * Tests for OD_Storage_Lock.
 *
 * @package optimization-detective
 *
 * @coversDefaultClass OD_Storage_Lock
 */

class Test_OD_Storage_Lock extends WP_UnitTestCase {

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		parent::tear_down();
	}

	/**
	 * Test add_hooks().
	 *
	 * @covers ::add_hooks
	 */
	public function test_add_hooks(): void {
		remove_all_filters( 'map_meta_cap' );

		OD_Storage_Lock::add_hooks();

		$this->assertSame(
			10,
			has_filter( 'map_meta_cap', array( OD_Storage_Lock::class, 'filter_map_meta_cap' ) )
		);
	}


	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_filter_map_meta_cap(): array {
		return array(
			'bad_caps_relevant'   => array(
				'caps'      => null,
				'cap'       => OD_Storage_Lock::STORE_URL_METRIC_NOW_CAPABILITY,
				'user_role' => 'administrator',
				'expected'  => array( 'manage_options' ),
			),
			'bad_caps_irrelevant' => array(
				'caps'      => null,
				'cap'       => 'edit_posts',
				'user_role' => 'administrator',
				'expected'  => array(),
			),
			'caps_authorized'     => array(
				'caps'      => array(),
				'cap'       => OD_Storage_Lock::STORE_URL_METRIC_NOW_CAPABILITY,
				'user_role' => 'administrator',
				'expected'  => array( 'manage_options' ),
			),
			'caps_unauthorized'   => array(
				'caps'      => array(),
				'cap'       => OD_Storage_Lock::STORE_URL_METRIC_NOW_CAPABILITY,
				'user_role' => 'subscriber',
				'expected'  => array(),
			),
			'caps_anonymous'      => array(
				'caps'      => array(),
				'cap'       => OD_Storage_Lock::STORE_URL_METRIC_NOW_CAPABILITY,
				'user_role' => null,
				'expected'  => array(),
			),
		);
	}

	/**
	 * Test filter_map_meta_cap().
	 *
	 * @dataProvider data_filter_map_meta_cap
	 * @covers ::filter_map_meta_cap
	 *
	 * @param string[]|mixed $caps      Primitive capabilities required of the user.
	 * @param string         $cap       Capability being checked.
	 * @param string|null    $user_role Current user role.
	 * @param string[]       $expected  Expected primitive capabilities required of the user.
	 */
	public function test_filter_map_meta_cap( $caps, string $cap, ?string $user_role, array $expected ): void {
		if ( null !== $user_role ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $user_role ) ) );
		}
		$return = OD_Storage_Lock::filter_map_meta_cap( $caps, $cap, wp_get_current_user()->ID );
		$this->assertSame( $expected, $return );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{set_up: Closure, expected: int}>
	 */
	public function data_provider_get_ttl(): array {
		return array(
			'unfiltered'         => array(
				'set_up'   => static function (): void {},
				'expected' => MINUTE_IN_SECONDS,
			),
			'unfiltered_admin'   => array(
				'set_up'   => static function (): void {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
				},
				'expected' => 0,
			),
			'filtered_hour'      => array(
				'set_up'   => static function (): void {
					add_filter(
						'od_url_metric_storage_lock_ttl',
						static function (): int {
							return HOUR_IN_SECONDS;
						}
					);
				},
				'expected' => HOUR_IN_SECONDS,
			),
			'filtered_negative'  => array(
				'set_up'   => static function (): void {
					add_filter(
						'od_url_metric_storage_lock_ttl',
						static function (): int {
							return -100;
						}
					);
				},
				'expected' => 0,
			),
			'granted_subscriber' => array(
				'set_up'   => static function (): void {
					add_filter(
						'map_meta_cap',
						static function ( array $caps, string $cap, int $user_id ): array {
							$primitive_cap = 'exist';
							if ( OD_Storage_Lock::STORE_URL_METRIC_NOW_CAPABILITY === $cap && user_can( $user_id, $primitive_cap ) ) {
								$caps = array( $primitive_cap );
							}
							return $caps;
						},
						10,
						3
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
	 * @covers ::filter_map_meta_cap
	 *
	 * @dataProvider data_provider_get_ttl
	 *
	 * @param Closure $set_up   Set up.
	 * @param int     $expected Expected value.
	 */
	public function test_get_ttl( Closure $set_up, int $expected ): void {
		$set_up();
		$this->assertSame( $expected, OD_Storage_Lock::get_ttl() );
	}

	/**
	 * Test get_transient_key().
	 *
	 * @covers ::get_transient_key
	 */
	public function test_get_transient_key(): void {
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
	public function test_set_lock_and_is_locked(): void {
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
