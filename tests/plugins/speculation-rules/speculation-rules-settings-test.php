<?php
/**
 * Tests for speculation-rules settings file.
 *
 * @package speculation-rules
 */

class Speculation_Rules_Settings_Tests extends WP_UnitTestCase {

	/**
	 * @covers ::plsr_register_setting
	 */
	public function test_plsr_register_setting() {
		unregister_setting( 'reading', 'plsr_speculation_rules' );
		$settings = get_registered_settings();
		$this->assertArrayNotHasKey( 'plsr_speculation_rules', $settings );

		plsr_register_setting();
		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'plsr_speculation_rules', $settings );
	}

	/**
	 * @covers ::plsr_sanitize_setting
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

	/**
	 * @covers ::plsr_add_settings_action_link
	 */
	public function test_plsr_add_settings_action_link() {
		$this->assertSame( 10, has_filter( 'plugin_action_links_' . SPECULATION_RULES_MAIN_FILE, 'plsr_add_settings_action_link' ) );
		$this->assertFalse( plsr_add_settings_action_link( false ) );

		$default_action_links = array(
			'deactivate' => '<a href="plugins.php?action=deactivate&amp;plugin=speculation-rules%2Fload.php&amp;plugin_status=all&amp;paged=1&amp;s&amp;_wpnonce=48f74bdd74" id="deactivate-speculation-rules" aria-label="Deactivate Speculative Loading">Deactivate</a>',
		);

		$this->assertSame(
			array_merge(
				$default_action_links,
				array(
					'settings' => '<a href="' . esc_url( admin_url( 'options-reading.php#speculative-loading' ) ) . '">Settings</a>',
				)
			),
			plsr_add_settings_action_link( $default_action_links )
		);
	}
}
