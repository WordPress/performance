<?php
/**
 * Tests for the View Transitions plugin includes/settings.php file.
 *
 * @package view-transitions
 * @group   view-transitions
 */

class Test_ViewTransitions_Settings extends WP_UnitTestCase {

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		remove_all_filters( 'plvt_show_settings_ui' );
		parent::tear_down();
	}

	/**
	 * @covers ::plvt_add_setting_ui
	 */
	public function test_plvt_add_setting_ui_enabled(): void {
		global $wp_settings_sections;

		plvt_add_setting_ui();

		$this->assertArrayHasKey( 'reading', $wp_settings_sections );
		$this->assertArrayHasKey( 'plvt_view_transitions', $wp_settings_sections['reading'] );
	}

	/**
	 * @covers ::plvt_add_setting_ui
	 */
	public function test_plvt_add_setting_ui_disabled_via_filter(): void {
		global $wp_settings_sections;

		// Reset settings section for 'reading'.
		unset( $wp_settings_sections['reading']['plvt_view_transitions'] );

		add_filter( 'plvt_show_settings_ui', '__return_false' );

		plvt_add_setting_ui();

		$this->assertArrayNotHasKey( 'plvt_view_transitions', $wp_settings_sections['reading'] ?? array() );
	}

	/**
	 * @covers ::plvt_add_settings_action_link
	 */
	public function test_plvt_add_settings_action_link_enabled(): void {
		$links    = array( 'deactivate' => '<a href="#">Deactivate</a>' );
		$modified = plvt_add_settings_action_link( $links );

		$this->assertArrayHasKey( 'settings', $modified );
		$this->assertStringContainsString( 'options-reading.php#view-transitions', $modified['settings'] );
		$this->assertArrayHasKey( 'deactivate', $modified );
	}

	/**
	 * @covers ::plvt_add_settings_action_link
	 */
	public function test_plvt_add_settings_action_link_disabled_via_filter(): void {
		add_filter( 'plvt_show_settings_ui', '__return_false' );

		$links    = array( 'deactivate' => '<a href="#">Deactivate</a>' );
		$modified = plvt_add_settings_action_link( $links );

		$this->assertArrayNotHasKey( 'settings', $modified );
		$this->assertSame( $links, $modified );
	}
}
