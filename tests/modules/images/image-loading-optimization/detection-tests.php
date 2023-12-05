<?php
/**
 * Tests for image-loading-optimization module detection.php.
 *
 * @package performance-lab
 * @group   image-loading-optimization
 */

use PerformanceLab\Tests\TestCase\ImagesTestCase;

class Image_Loading_Optimization_Detection_Tests extends ImagesTestCase {

	/**
	 * Data provider.
	 *
	 * @return array<string, array{set_up: Closure, expected_exports: array<string, mixed>}>
	 */
	public function data_provider_ilo_get_detection_script(): array {
		return array(
			'no_filters' => array(
				'set_up' => static function () {},
				'expected_exports' => array(
					'detectionTimeWindow' => 5000,
					'storageLockTTL' => MINUTE_IN_SECONDS,
				),
			),
			'filtered' => array(
				'set_up' => static function () {
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
					'storageLockTTL' => HOUR_IN_SECONDS,
				),
			),
		);
	}

	/**
	 * Make sure the expected script is printed.
	 *
	 * @test
	 * @dataProvider data_provider_ilo_get_detection_script
	 * @covers ::ilo_get_detection_script
	 *
	 * @param Closure                                                                       $set_up           Set up callback.
	 * @param array<string, array{set_up: Closure, expected_exports: array<string, mixed>}> $expected_exports Expected exports.
	 */
	public function test_ilo_get_detection_script_returns_script( Closure $set_up, array $expected_exports ) {
		$set_up();
		$slug = ilo_get_url_metrics_slug( array( 'p' => '1' ) );
		$needed_minimum_viewport_widths = array(
			array( 480, false ),
			array( 600, false ),
			array( 782, true )
		);
		$script = ilo_get_detection_script( $slug, $needed_minimum_viewport_widths );

		$this->assertStringContainsString( '<script type="module">', $script );
		$this->assertStringContainsString( 'import detect from', $script );
		foreach ( $expected_exports as $key => $value ) {
			$this->assertStringContainsString( sprintf( '%s:%s', wp_json_encode( $key ), wp_json_encode( $value ) ), $script );
		}
	}
}
