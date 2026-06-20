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
	 * @covers ::plsr_get_authentication_labels
	 * @covers ::plsr_get_setting_default
	 * @covers ::plsr_get_field_description
	 */
	public function test_plsr_register_setting(): void {
		unregister_setting( 'reading', 'plsr_speculation_rules' );
		$settings = get_registered_settings();
		$this->assertArrayNotHasKey( 'plsr_speculation_rules', $settings );

		plsr_register_setting();
		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'plsr_speculation_rules', $settings );
		foreach ( array( 'mode', 'eagerness', 'authentication' ) as $key ) {
			$this->assertTrue( isset( $settings['plsr_speculation_rules']['show_in_rest']['schema']['properties'][ $key ]['description'] ) );
			$description = $settings['plsr_speculation_rules']['show_in_rest']['schema']['properties'][ $key ]['description'];
			$this->assertIsString( $description );
			$this->assertGreaterThan( 0, strlen( $description ) );
			$this->assertStringNotContainsString( '<', $description );
		}

		$settings = plsr_get_setting_default();
		$this->assertArrayHasKey( 'mode', $settings );
		$this->assertArrayHasKey( 'eagerness', $settings );
		$this->assertArrayHasKey( 'authentication', $settings );

		// Test default settings applied correctly.
		$default_settings = plsr_get_setting_default();
		$this->assertEquals( $default_settings, get_option( 'plsr_speculation_rules' ) );

		foreach ( array( 'mode', 'eagerness', 'authentication' ) as $key ) {
			$description = plsr_get_field_description( $key );
			$this->assertGreaterThan( 0, strlen( $description ) );
		}
		$this->assertSame( '', plsr_get_field_description( 'bogus' ) );
	}

	/**
	 * @covers ::plsr_sanitize_setting
	 * @covers ::plsr_get_mode_labels
	 * @covers ::plsr_get_eagerness_labels
	 * @covers ::plsr_get_authentication_labels
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
			'mode'               => 'prerender',
			'eagerness'          => 'moderate',
			'authentication'     => 'logged_out',
			'origin_trial_token' => '',
		);

		return array(
			'invalid type null'                => array(
				null,
				$default_value,
			),
			'invalid type string'              => array(
				'prerender',
				$default_value,
			),
			'missing fields'                   => array(
				array(),
				$default_value,
			),
			'missing mode'                     => array(
				array( 'eagerness' => 'conservative' ),
				array_merge(
					$default_value,
					array(
						'eagerness' => 'conservative',
					)
				),
			),
			'missing eagerness'                => array(
				array( 'mode' => 'prefetch' ),
				array_merge(
					$default_value,
					array(
						'mode' => 'prefetch',
					)
				),
			),
			'invalid mode'                     => array(
				array(
					'mode'      => 'something',
					'eagerness' => 'eager',
				),
				array_merge(
					$default_value,
					array(
						'mode'      => 'prerender',
						'eagerness' => 'eager',
					)
				),
			),
			'invalid eagerness'                => array(
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'something',
				),
				array_merge(
					$default_value,
					array(
						'mode'      => 'prefetch',
						'eagerness' => 'moderate',
					)
				),
			),
			'invalid authentication'           => array(
				array(
					'authentication' => 'bad',
				),
				$default_value,
			),
			'valid auth logged_out'            => array(
				array(
					'authentication' => 'logged_out',
				),
				array_merge(
					$default_value,
					array(
						'authentication' => 'logged_out',
					)
				),
			),
			'valid auth logged_out_and_admins' => array(
				array(
					'authentication' => 'logged_out_and_admins',
				),
				array_merge(
					$default_value,
					array(
						'authentication' => 'logged_out_and_admins',
					)
				),
			),
			'valid auth any'                   => array(
				array(
					'authentication' => 'any',
				),
				array_merge(
					$default_value,
					array(
						'authentication' => 'any',
					)
				),
			),
			'valid fields'                     => array(
				array(
					'mode'           => 'prefetch',
					'eagerness'      => 'conservative',
					'authentication' => 'logged_out_and_admins',
				),
				array_merge(
					$default_value,
					array(
						'mode'           => 'prefetch',
						'eagerness'      => 'conservative',
						'authentication' => 'logged_out_and_admins',
					)
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
				'mode'               => 'prefetch',
				'eagerness'          => 'moderate',
				'authentication'     => 'logged_out',
				'origin_trial_token' => '',
			)
		);
		$settings = plsr_get_stored_setting_value();
		$this->assertEquals(
			array(
				'mode'               => 'prefetch',
				'eagerness'          => 'moderate',
				'authentication'     => 'logged_out',
				'origin_trial_token' => '',
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
			'mode'               => array(
				'field'       => 'mode',
				'value'       => 'prefetch',
				'title'       => 'Speculation Mode',
				'description' => 'The mode description',
			),
			'eagerness'          => array(
				'field'       => 'eagerness',
				'value'       => 'moderate',
				'title'       => 'Eagerness',
				'description' => 'The eagerness description',
			),
			'authentication'     => array(
				'field'       => 'authentication',
				'value'       => 'any',
				'title'       => 'Authentication',
				'description' => 'The authentication description.',
			),
			'origin_trial_token' => array(
				'field'       => 'origin_trial_token',
				'value'       => 'some_token',
				'title'       => 'Origin Trial Token',
				'description' => 'The origin trial token description.',
			),
		);
	}

	/**
	 * Test rendering of settings fields using data provider.
	 *
	 * @dataProvider data_provider_to_test_render_settings_field
	 *
	 * @param string $field Field.
	 * @param string $value Value.
	 * @param string $title Title.
	 * @param string $description Description.
	 */
	public function test_plsr_render_settings_field( string $field, string $value, string $title, string $description ): void {
		// Simulate getting stored settings.
		update_option( 'plsr_speculation_rules', array( $field => $value ) );

		// Capture the output of the settings field rendering.
		$output = get_echo( 'plsr_render_settings_field', array( compact( 'field', 'title', 'description' ) ) );

		// Check for the presence of form elements.
		$this->assertStringContainsString( $description, $output );

		$p     = new WP_HTML_Tag_Processor( $output );
		$found = false;
		while ( $p->next_tag( array( 'tag_name' => 'INPUT' ) ) ) {
			if (
				$p->get_attribute( 'name' ) === sprintf( 'plsr_speculation_rules[%s]', $field )
				&&
				$p->get_attribute( 'value' ) === $value
			) {
				if ( 'origin_trial_token' === $field ) {
					$found = true;
				} else {
					$found = null !== $p->get_attribute( 'checked' );
				}
				break;
			}
		}
		$this->assertTrue( $found, $output );
	}
}
