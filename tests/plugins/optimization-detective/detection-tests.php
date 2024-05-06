<?php
/**
 * Tests for optimization-detective plugin detection.php.
 *
 * @package optimization-detective
 */

class OD_Detection_Tests extends WP_UnitTestCase {

	/**
	 * Data provider.
	 *
	 * @return array<string, array{set_up: Closure, expected_exports: array<string, mixed>}>
	 */
	public function data_provider_od_get_detection_script(): array {
		return array(
			'unfiltered' => array(
				'set_up'           => static function () {},
				'expected_exports' => array(
					'detectionTimeWindow' => 5000,
					'storageLockTTL'      => MINUTE_IN_SECONDS,
				),
			),
			'filtered'   => array(
				'set_up'           => static function () {
					add_filter(
						'od_detection_time_window',
						static function (): int {
							return 2500;
						}
					);
					add_filter(
						'od_url_metric_storage_lock_ttl',
						static function (): int {
							return HOUR_IN_SECONDS;
						}
					);
				},
				'expected_exports' => array(
					'detectionTimeWindow' => 2500,
					'storageLockTTL'      => HOUR_IN_SECONDS,
				),
			),
		);
	}

	/**
	 * Make sure the expected script is printed.
	 *
	 * @covers ::od_get_detection_script
	 *
	 * @dataProvider data_provider_od_get_detection_script
	 *
	 * @param Closure                                                                       $set_up           Set up callback.
	 * @param array<string, array{set_up: Closure, expected_exports: array<string, mixed>}> $expected_exports Expected exports.
	 */
	public function test_od_get_detection_script_returns_script( Closure $set_up, array $expected_exports ): void {
		$set_up();
		$slug = od_get_url_metrics_slug( array( 'p' => '1' ) );

		$breakpoints      = array( 480, 600, 782 );
		$group_collection = new OD_URL_Metrics_Group_Collection( array(), $breakpoints, 3, HOUR_IN_SECONDS );

		$script = od_get_detection_script( $slug, $group_collection );

		$this->assertStringContainsString( '<script type="module">', $script );
		$this->assertStringContainsString( 'import detect from', $script );
		foreach ( $expected_exports as $key => $value ) {
			$this->assertStringContainsString( sprintf( '%s:%s', wp_json_encode( $key ), wp_json_encode( $value ) ), $script );
		}
		$this->assertStringContainsString( '"minimumViewportWidth":0', $script );
		$this->assertStringContainsString( '"minimumViewportWidth":481', $script );
		$this->assertStringContainsString( '"minimumViewportWidth":601', $script );
		$this->assertStringContainsString( '"minimumViewportWidth":783', $script );
		$this->assertStringContainsString( '"complete":false', $script );
	}
}
