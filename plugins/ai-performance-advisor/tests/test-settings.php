<?php
/**
 * Tests for the settings integration.
 *
 * @package ai-performance-advisor
 */

declare( strict_types = 1 );

class AIPA_Test_Settings extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( 'aipa_settings' );
		parent::tear_down();
	}

	/**
	 * @covers ::aipa_sanitize_settings
	 */
	public function test_sanitize_settings_returns_defaults_for_non_array(): void {
		$sanitized = aipa_sanitize_settings( 'not-an-array' );
		$this->assertSame( aipa_get_default_settings(), $sanitized );
	}

	/**
	 * @covers ::aipa_sanitize_settings
	 */
	public function test_sanitize_settings_casts_and_sanitizes(): void {
		$sanitized = aipa_sanitize_settings(
			array(
				'include_pagespeed' => '1',
				'pagespeed_api_key' => "  abc<script>123  \n",
			)
		);
		$this->assertTrue( $sanitized['include_pagespeed'] );
		$this->assertSame( 'abc123', $sanitized['pagespeed_api_key'] );
	}

	/**
	 * @covers ::aipa_sanitize_settings
	 */
	public function test_sanitize_settings_defaults_missing_fields(): void {
		$sanitized = aipa_sanitize_settings( array() );
		$this->assertFalse( $sanitized['include_pagespeed'] );
		$this->assertSame( '', $sanitized['pagespeed_api_key'] );
	}

	/**
	 * @covers ::aipa_register_setting
	 */
	public function test_register_setting_registers_option(): void {
		aipa_register_setting();
		$this->assertArrayHasKey( 'aipa_settings', get_registered_settings() );
	}

	/**
	 * @covers ::aipa_add_settings_ui
	 */
	public function test_add_settings_ui_registers_section_and_fields(): void {
		global $wp_settings_sections, $wp_settings_fields;

		aipa_add_settings_ui();

		$this->assertArrayHasKey( 'aipa_settings', $wp_settings_sections['general'] );
		$this->assertArrayHasKey( 'aipa_include_pagespeed', $wp_settings_fields['general']['aipa_settings'] );
		$this->assertArrayHasKey( 'aipa_pagespeed_api_key', $wp_settings_fields['general']['aipa_settings'] );
	}

	/**
	 * @covers ::aipa_render_include_pagespeed_field
	 */
	public function test_render_include_pagespeed_field(): void {
		update_option( 'aipa_settings', array( 'include_pagespeed' => true ) );
		$html = $this->capture( 'aipa_render_include_pagespeed_field' );
		$this->assertStringContainsString( 'name="aipa_settings[include_pagespeed]"', $html );
		$this->assertStringContainsString( 'checked', $html );
	}

	/**
	 * @covers ::aipa_render_pagespeed_api_key_field
	 */
	public function test_render_pagespeed_api_key_field(): void {
		update_option( 'aipa_settings', array( 'pagespeed_api_key' => 'my-key' ) );
		$html = $this->capture( 'aipa_render_pagespeed_api_key_field' );
		$this->assertStringContainsString( 'name="aipa_settings[pagespeed_api_key]"', $html );
		$this->assertStringContainsString( 'value="my-key"', $html );
	}

	/**
	 * @covers ::aipa_add_settings_action_link
	 */
	public function test_add_settings_action_link_prepends_settings_link(): void {
		$links = aipa_add_settings_action_link( array( '<a href="#">Deactivate</a>' ) );
		$this->assertArrayHasKey( 'settings', $links );
		$this->assertStringContainsString( 'options-general.php', $links['settings'] );
		$this->assertStringContainsString( 'Settings', $links['settings'] );
	}

	/**
	 * @covers ::aipa_add_settings_action_link
	 */
	public function test_add_settings_action_link_ignores_non_array(): void {
		$this->assertSame( 'unexpected', aipa_add_settings_action_link( 'unexpected' ) );
	}

	/**
	 * Captures the output of a callable that echoes markup.
	 *
	 * @param callable $callback Callback to invoke.
	 * @return string Captured output.
	 */
	private function capture( callable $callback ): string {
		ob_start();
		call_user_func( $callback );
		return (string) ob_get_clean();
	}
}
