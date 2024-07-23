<?php
/**
 * Tests for admin/server-timing.php
 *
 * @package performance-lab
 */

/**
 * @group admin
 * @group server-timing
 */
class Test_Admin_Server_Timing extends WP_UnitTestCase {

	/**
	 * @covers ::perflab_add_server_timing_page
	 */
	public function test_perflab_add_server_timing_page(): void {
		global $_wp_submenu_nopriv;

		// Reset relevant globals and filters.
		$_wp_submenu_nopriv = array();
		remove_all_filters( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ) );

		// Rely on current user to be an administrator (with 'manage_options' capability).
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$hook_suffix = get_plugin_page_hookname( PERFLAB_SERVER_TIMING_SCREEN, 'tools.php' );
		$this->assertFalse( has_action( "load-{$hook_suffix}", 'perflab_load_server_timing_page' ) );
		perflab_add_server_timing_page();
		$this->assertSame( 10, has_action( "load-{$hook_suffix}", 'perflab_load_server_timing_page' ) );
		$this->assertArrayNotHasKey( 'tools.php', $_wp_submenu_nopriv );
	}

	/**
	 * @covers ::perflab_add_server_timing_page
	 */
	public function test_perflab_add_server_timing_page_missing_caps(): void {
		global $_wp_submenu_nopriv;

		// Reset relevant globals and filters.
		$_wp_submenu_nopriv = array();
		remove_all_filters( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ) );

		// The default user does not have the 'manage_options' capability.
		$hook_suffix = get_plugin_page_hookname( PERFLAB_SERVER_TIMING_SCREEN, 'tools.php' );
		$this->assertFalse( has_action( "load-{$hook_suffix}", 'perflab_load_server_timing_page' ) );
		perflab_add_server_timing_page();
		$this->assertFalse( has_action( "load-{$hook_suffix}", 'perflab_load_server_timing_page' ) );
		$this->assertArrayHasKey( 'tools.php', $_wp_submenu_nopriv );
		$this->assertArrayHasKey( PERFLAB_SERVER_TIMING_SCREEN, $_wp_submenu_nopriv['tools.php'] );
	}

	public function test_perflab_load_server_timing_page(): void {
		global $wp_settings_sections, $wp_settings_fields;

		// Reset relevant globals.
		$wp_settings_sections = array();
		$wp_settings_fields   = array();

		perflab_load_server_timing_page();
		$this->assertArrayHasKey( PERFLAB_SERVER_TIMING_SCREEN, $wp_settings_sections );
		$expected_sections = array( 'benchmarking' );
		if ( false === has_filter( 'template_include', 'od_buffer_output' ) ) {
			$expected_sections[] = 'output-buffering';
		}
		$this->assertEqualSets(
			$expected_sections,
			array_keys( $wp_settings_sections[ PERFLAB_SERVER_TIMING_SCREEN ] )
		);
		$this->assertArrayHasKey( PERFLAB_SERVER_TIMING_SCREEN, $wp_settings_fields );
		$this->assertEqualSets(
			array( 'benchmarking' ),
			array_keys( $wp_settings_fields[ PERFLAB_SERVER_TIMING_SCREEN ] )
		);
		$this->assertEqualSets(
			array( 'benchmarking_actions', 'benchmarking_filters' ),
			array_keys( $wp_settings_fields[ PERFLAB_SERVER_TIMING_SCREEN ]['benchmarking'] )
		);
	}

	public function test_perflab_render_server_timing_page(): void {
		ob_start();
		perflab_render_server_timing_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div class="wrap">', $output );
		$this->assertStringContainsString( "<input type='hidden' name='option_page' value='" . PERFLAB_SERVER_TIMING_SCREEN . "' />", $output );
	}

	public function test_perflab_render_server_timing_page_field(): void {
		$slug = 'benchmarking_actions';

		ob_start();
		perflab_render_server_timing_page_hooks_field( $slug );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<textarea', $output );
		$this->assertStringContainsString( 'id="server_timing_' . $slug . '"', $output );
		$this->assertStringContainsString( 'name="' . PERFLAB_SERVER_TIMING_SETTING . '[' . $slug . ']"', $output );
	}

	public function test_perflab_render_server_timing_page_field_empty_option(): void {
		delete_option( PERFLAB_SERVER_TIMING_SETTING );

		ob_start();
		perflab_render_server_timing_page_hooks_field( 'benchmarking_actions' );
		$output = ob_get_clean();

		$this->assertStringContainsString( '></textarea>', $output );
	}

	public function test_perflab_render_server_timing_page_field_populated_option(): void {
		update_option(
			PERFLAB_SERVER_TIMING_SETTING,
			array( 'benchmarking_actions' => array( 'init', 'wp_loaded' ) )
		);

		ob_start();
		perflab_render_server_timing_page_hooks_field( 'benchmarking_actions' );
		$output = ob_get_clean();

		// Array is formatted/imploded as strings, one per line.
		$this->assertStringContainsString( ">init\nwp_loaded</textarea>", $output );
	}
}
