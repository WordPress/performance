<?php
/**
 * Tests for admin/load.php
 *
 * @package performance-lab
 */

/**
 * @group admin
 */
class Admin_Load_Tests extends WP_UnitTestCase {

	/**
	 * @covers ::perflab_add_features_page
	 */
	public function test_perflab_add_features_page() {
		global $_wp_submenu_nopriv;

		// Reset relevant globals and filters.
		$_wp_submenu_nopriv = array();
		remove_all_filters( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ) );

		$hook_suffix = get_plugin_page_hookname( PERFLAB_SCREEN, 'tools.php' );

		// The default user does not have the 'manage_options' capability.
		perflab_add_features_page();
		$this->assertFalse( has_action( "load-{$hook_suffix}", 'perflab_load_features_page' ) );
		$this->assertArrayHasKey( 'options-general.php', $_wp_submenu_nopriv );
		$this->assertArrayHasKey( PERFLAB_SCREEN, $_wp_submenu_nopriv['options-general.php'] );
		// Ensure plugin action link is not added.
		$this->assertFalse( (bool) has_action( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ), 'perflab_plugin_action_links_add_settings' ) );

		// Reset relevant globals and filters.
		$_wp_submenu_nopriv = array();
		remove_all_filters( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ) );

		// Rely on current user to be an administrator (with 'manage_options' capability).
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$this->assertFalse( has_action( "load-{$hook_suffix}", 'perflab_load_features_page' ) );
		perflab_add_features_page();
		$this->assertSame( 10, has_action( "load-{$hook_suffix}", 'perflab_load_features_page' ) );

		$this->assertSame( get_plugin_page_hookname( PERFLAB_SCREEN, 'options-general.php' ), $hook_suffix );
		$this->assertArrayNotHasKey( 'options-general.php', $_wp_submenu_nopriv );
		// Ensure plugin action link is added.
		$this->assertTrue( (bool) has_action( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ), 'perflab_plugin_action_links_add_settings' ) );

		// Reset relevant globals and filters.
		$_wp_submenu_nopriv = array();
		remove_all_filters( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ) );
	}

	public function test_perflab_render_settings_page() {
		ob_start();
		perflab_render_settings_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( '<div class="wrap">', $output );
		$this->assertStringNotContainsString( "<input type='hidden' name='option_page' value='" . PERFLAB_SCREEN . "' />", $output );
	}

	public function test_perflab_plugin_action_links_add_settings() {
		$original_links = array(
			'deactivate' => '<a href="#">Deactivate</a>',
		);
		$expected_links = array_merge(
			array(
				'settings' => '<a href="' . admin_url( '/' ) . 'options-general.php?page=' . PERFLAB_SCREEN . '">Settings</a>',
			),
			$original_links
		);

		$actual_links = perflab_plugin_action_links_add_settings( $original_links );
		$this->assertSame( $expected_links, $actual_links );
	}
}
