<?php
/**
 * Tests for speculation-rules settings file.
 *
 * @package speculation-rules
 */

class Test_Speculation_Rules_Settings extends WP_UnitTestCase {

	/**
	 * @covers ::plsr_register_setting
	 * @covers ::plsr_get_mode_labels
	 * @covers ::plsr_get_eagerness_labels
	 * @covers ::plsr_get_setting_default
	 */
	public function test_plsr_register_setting(): void {
		unregister_setting( 'reading', 'plsr_speculation_rules' );
		$settings = get_registered_settings();
		$this->assertArrayNotHasKey( 'plsr_speculation_rules', $settings );

		plsr_register_setting();
		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'plsr_speculation_rules', $settings );

		$settings = plsr_get_setting_default();
		$this->assertArrayHasKey( 'mode', $settings );
		$this->assertArrayHasKey( 'eagerness', $settings );

		// Test default settings applied correctly.
		$default_settings = plsr_get_setting_default();
		$this->assertEquals( $default_settings, get_option( 'plsr_speculation_rules' ) );
	}

	/**
	 * @covers ::plsr_sanitize_setting
	 * @dataProvider data_plsr_sanitize_setting
	 *
	 * @param mixed                $input    Input.
	 * @param array<string, mixed> $expected Expected.
	 */
	public function test_plsr_sanitize_setting( $input, array $expected ): void {
		$this->assertSameSets(
			$expected,
			plsr_sanitize_setting( $input )
		);
	}

	/** @return array<string, mixed> */
	public function data_plsr_sanitize_setting(): array {
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
	public function test_plsr_add_settings_action_link(): void {
		$this->assertSame( 10, has_filter( 'plugin_action_links_' . SPECULATION_RULES_MAIN_FILE, 'plsr_add_settings_action_link' ) );
		$this->assertFalse( plsr_add_settings_action_link( false ) );

		$default_action_links = array(
			'deactivate' => '<a href="plugins.php?action=deactivate&amp;plugin=speculation-rules%2Fload.php&amp;plugin_status=all&amp;paged=1&amp;s&amp;_wpnonce=48f74bdd74" id="deactivate-speculation-rules" aria-label="Deactivate Speculative Loading">Deactivate</a>',
		);

		$this->assertSame(
			array_merge(
				array(
					'settings' => '<a href="' . esc_url( admin_url( 'options-reading.php#speculative-loading' ) ) . '">Settings</a>',
				),
				$default_action_links
			),
			plsr_add_settings_action_link( $default_action_links )
		);
	}

	/**
	 * @covers ::plsr_get_stored_setting_value
	 */
	public function test_get_stored_setting_value(): void {
		update_option(
			'plsr_speculation_rules',
			array(
				'mode'      => 'prefetch',
				'eagerness' => 'moderate',
			)
		);
		$settings = plsr_get_stored_setting_value();
		$this->assertEquals(
			array(
				'mode'      => 'prefetch',
				'eagerness' => 'moderate',
			),
			$settings
		);

		// Test default when no option is set.
		delete_option( 'plsr_speculation_rules' );
		$settings = plsr_get_stored_setting_value();
		$this->assertEquals( plsr_get_setting_default(), $settings );
	}

	/**
	 * Function to test sanitize_setting() with various inputs.
	 */
	public function test_plsr_sanitize_setting_with_invalid_inputs(): void {

		$input     = array(
			'mode'      => 'invalid_mode',
			'eagerness' => 'conservative',
		);
		$sanitized = plsr_sanitize_setting( $input );
		$this->assertEquals( 'prerender', $sanitized['mode'] );

		$input     = array(
			'mode'      => 'prefetch',
			'eagerness' => 'invalid_eagerness',
		);
		$sanitized = plsr_sanitize_setting( $input );
		$this->assertEquals( 'moderate', $sanitized['eagerness'] );

		$input     = 'invalid_input';
		$sanitized = plsr_sanitize_setting( $input );
		$this->assertEquals( plsr_get_setting_default(), $sanitized );
	}

	/**
	 * @covers ::plsr_add_setting_ui
	 */
	public function test_plsr_add_setting_ui(): void {
		do_action( 'load-options-reading.php' );// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		// Check if the settings section has been added.
		global $wp_settings_sections;
		$this->assertArrayHasKey( 'reading', $wp_settings_sections );
		$this->assertArrayHasKey( 'plsr_speculation_rules', $wp_settings_sections['reading'] );

		// Check the output of the callback function for the section.
		$output = get_echo( $wp_settings_sections['reading']['plsr_speculation_rules']['callback'] );
		$this->assertStringContainsString( 'This section allows you to control how URLs that your users navigate to are speculatively loaded to improve performance.', $output );
	}

	/**
	 * Data provider for testing plsr_render_settings_field.
	 *
	 * @return array<string, array<mixed>> Data for testing settings fields.
	 */
	public function data_provider_to_test_render_settings_field(): array {
		return array(
			'mode'      => array(
				array(
					'field'       => 'mode',
					'title'       => 'Speculation Mode',
					'description' => 'Prerendering will lead to faster load times than prefetching.',
				),
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'moderate',
				),
				'name="plsr_speculation_rules[mode]"',
				'value="prefetch"',
				'Prerendering will lead to faster load times than prefetching.',
			),
			'eagerness' => array(
				array(
					'field'       => 'eagerness',
					'title'       => 'Eagerness',
					'description' => 'The eagerness setting defines the heuristics based on which the loading is triggered.',
				),
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'moderate',
				),
				'name="plsr_speculation_rules[eagerness]"',
				'value="moderate"',
				'The eagerness setting defines the heuristics based on which the loading is triggered.',
			),
		);
	}

	/**
	 * Test rendering of settings fields using data provider.
	 *
	 * @dataProvider data_provider_to_test_render_settings_field
	 * @param array<mixed> $args Arguments for the settings field.
	 * @param array<mixed> $stored_settings Stored settings values.
	 * @param string       $name_check HTML name attribute check.
	 * @param string       $value_check HTML value attribute check.
	 * @param string       $description_check Description check.
	 */
	public function test_render_settings_field( array $args, array $stored_settings, string $name_check, string $value_check, string $description_check ): void {
		// Simulate getting stored settings.
		update_option( 'plsr_speculation_rules', $stored_settings );

		// Capture the output of the settings field rendering.
		$output = get_echo( 'plsr_render_settings_field', array( $args ) );

		// Check for the presence of form elements.
		$this->assertStringContainsString( $name_check, $output );
		$this->assertStringContainsString( $value_check, $output );
		$this->assertStringContainsString( $description_check, $output );
	}
}
