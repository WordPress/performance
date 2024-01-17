<?php
/**
 * Tests for speculation-rules settings file.
 *
 * @package performance-lab
 * @group speculation-rules
 */

class Speculation_Rules_Settings_Tests extends WP_UnitTestCase {

	public function test_plsr_register_setting() {
		unregister_setting( 'reading', 'plsr_speculation_rules' );
		$settings = get_registered_settings();
		$this->assertArrayNotHasKey( 'plsr_speculation_rules', $settings );

		plsr_register_setting();
		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'plsr_speculation_rules', $settings );
	}

	/**
	 * @dataProvider data_plsr_sanitize_setting
	 */
	public function test_plsr_sanitize_setting( $input, array $expected ) {
		$this->assertSameSets(
			$expected,
			plsr_sanitize_setting( $input )
		);
	}

	public function data_plsr_sanitize_setting() {
		$default_value = array(
			'mode'      => 'prerender',
			'eagerness' => 'moderate',
		);

		return array(
			'invalid type null'   => array(
				null,
				$default_value,
			),
			'invalid type string' => array(
				'prerender',
				$default_value,
			),
			'missing fields'      => array(
				array(),
				$default_value,
			),
			'missing mode'        => array(
				array( 'eagerness' => 'conservative' ),
				array(
					'mode'      => $default_value['mode'],
					'eagerness' => 'conservative',
				),
			),
			'missing eagerness'   => array(
				array( 'mode' => 'prefetch' ),
				array(
					'mode'      => 'prefetch',
					'eagerness' => $default_value['eagerness'],
				),
			),
			'invalid mode'        => array(
				array(
					'mode'      => 'something',
					'eagerness' => 'eager',
				),
				array(
					'mode'      => 'prerender',
					'eagerness' => 'eager',
				),
			),
			'invalid eagerness'   => array(
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'something',
				),
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'moderate',
				),
			),
			'valid fields'        => array(
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
			),
		);
	}
}
