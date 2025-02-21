<?php
/**
 * Test cases for seo-by-rank-math.php in Web Worker Offloading.
 *
 * @package web-worker-offloading
 */

class Test_Seo_By_Rank_Math extends WP_UnitTestCase {
	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up(): void {
		parent::set_up();
		require_once __DIR__ . '/../../third-party/seo-by-rank-math.php';
	}

	/**
	 * Test the function that configures WWO for Rank Math SEO.
	 */
	public function test_plwwo_rank_math_configure(): void {
		$configuration          = array();
		$expected_configuration = array(
			'globalFns' => array( 'gtag' ),
			'forward'   => array( 'dataLayer.push', 'gtag' ),
		);

		$result = plwwo_rank_math_configure( $configuration );

		$this->assertEquals( $expected_configuration, $result );
	}

	/**
	 * Test the function that filters script attributes.
	 *
	 * @covers ::plwwo_rank_math_filter_script_attributes
	 */
	public function test_plwwo_rank_math_filter_script_attributes(): void {
		$attributes          = array( 'id' => 'google_gtagjs' );
		$expected_attributes = array(
			'id'   => 'google_gtagjs',
			'type' => 'text/partytown',
		);

		$result = plwwo_rank_math_filter_script_attributes( $attributes );

		$this->assertEquals( $expected_attributes, $result );
	}

	/**
	 * Test the function that filters inline script attributes.
	 *
	 * @covers ::plwwo_rank_math_filter_inline_script_attributes
	 */
	public function test_plwwo_rank_math_filter_inline_script_attributes(): void {
		$attributes          = array( 'id' => 'google_gtagjs-inline' );
		$expected_attributes = array(
			'id'   => 'google_gtagjs-inline',
			'type' => 'text/partytown',
		);

		$result = plwwo_rank_math_filter_inline_script_attributes( $attributes );

		$this->assertEquals( $expected_attributes, $result );
	}
}
