<?php
/**
 * Tests for image-loading-optimization module detection.php.
 *
 * @package performance-lab
 * @group   image-loading-optimization
 */

class ILO_Detection_Tests extends WP_UnitTestCase {

	/**
	 * Data provider.
	 *
	 * @return array<string, array{set_up: Closure, expected_exports: array<string, mixed>}>
	 */
	public function data_provider_ilo_get_detection_script(): array {
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
						'ilo_detection_time_window',
						static function (): int {
							return 2500;
						}
					);
					add_filter(
						'ilo_url_metric_storage_lock_ttl',
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
	 * @covers ::ilo_get_detection_script
	 *
	 * @dataProvider data_provider_ilo_get_detection_script
	 *
	 * @param Closure                                                                       $set_up           Set up callback.
	 * @param array<string, array{set_up: Closure, expected_exports: array<string, mixed>}> $expected_exports Expected exports.
	 */
	public function test_ilo_get_detection_script_returns_script( Closure $set_up, array $expected_exports ) {
		$set_up();
		$slug           = ilo_get_url_metrics_slug( array( 'p' => '1' ) );
		$group_statuses = array_map(
			static function ( array $args ) {
				return new ILO_URL_Metrics_Group_Status( ...$args );
			},
			array(
				array( 480, false ),
				array( 600, false ),
				array( 782, true ),
			)
		);

		$script = ilo_get_detection_script( $slug, $group_statuses );

		$this->assertStringContainsString( '<script type="module">', $script );
		$this->assertStringContainsString( 'import detect from', $script );
		foreach ( $expected_exports as $key => $value ) {
			$this->assertStringContainsString( sprintf( '%s:%s', wp_json_encode( $key ), wp_json_encode( $value ) ), $script );
		}
		$this->assertStringContainsString( '"minimumViewportWidth":480', $script );
		$this->assertStringContainsString( '"isLacking":false', $script );
	}
}
