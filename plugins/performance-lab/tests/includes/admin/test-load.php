<?php
/**
 * Tests for admin/load.php
 *
 * @package performance-lab
 */

/**
 * @group admin
 */
class Test_Admin_Load extends WP_UnitTestCase {

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->reset_wp_dependencies();
	}

	/**
	 * After a test method runs, resets any state in WordPress the test method might have changed.
	 */
	public function tear_down(): void {
		parent::tear_down();
		$this->reset_wp_dependencies();
	}

	/**
	 * Reset WP_Scripts and WP_Styles.
	 */
	private function reset_wp_dependencies(): void {
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
	}

	/**
	 * @covers ::perflab_add_features_page
	 */
	public function test_perflab_add_features_page(): void {
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

	/**
	 * @covers ::perflab_render_settings_page
	 */
	public function test_perflab_render_settings_page(): void {
		ob_start();
		perflab_render_settings_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( '<div class="wrap">', $output );
		$this->assertStringNotContainsString( "<input type='hidden' name='option_page' value='" . PERFLAB_SCREEN . "' />", $output );
	}

	/**
	 * @covers ::perflab_get_dismissed_admin_pointer_ids
	 */
	public function test_perflab_get_dismissed_admin_pointer_ids(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// No dismissed pointers.
		$this->assertSame( array(), perflab_get_dismissed_admin_pointer_ids() );

		// Dismiss a single pointer.
		update_user_meta( $user_id, 'dismissed_wp_pointers', 'perflab-admin-pointer' );
		$this->assertSame( array( 'perflab-admin-pointer' ), perflab_get_dismissed_admin_pointer_ids() );

		// Dismiss multiple pointers.
		update_user_meta( $user_id, 'dismissed_wp_pointers', 'perflab-admin-pointer,another-pointer' );
		$this->assertSame( array( 'perflab-admin-pointer', 'another-pointer' ), perflab_get_dismissed_admin_pointer_ids() );

		// Dismiss all pointers.
		update_user_meta( $user_id, 'dismissed_wp_pointers', implode( ',', array_keys( perflab_get_admin_pointers() ) ) );
		$this->assertSame( array_keys( perflab_get_admin_pointers() ), perflab_get_dismissed_admin_pointer_ids() );
	}

	/**
	 * @covers ::perflab_get_admin_pointers
	 */
	public function test_perflab_get_admin_pointers(): void {
		$pointers = perflab_get_admin_pointers();
		$this->assertArrayHasKey( 'perflab-admin-pointer', $pointers );
	}

	/**
	 * @return array<string, array{
	 *     initial_wp_pointers: string,
	 *     hook_suffix: string|null,
	 *     expected: bool,
	 *     dismissed_wp_pointers: string,
	 * }>
	 */
	public function data_provider_test_perflab_admin_pointer(): array {
		return array(
			'null'                       => array(
				'initial_wp_pointers'   => '',
				'hook_suffix'           => null,
				'expected'              => false,
				'dismissed_wp_pointers' => '',
			),
			'edit.php'                   => array(
				'initial_wp_pointers'   => '',
				'hook_suffix'           => 'edit.php',
				'expected'              => false,
				'dismissed_wp_pointers' => '',
			),
			'dashboard_not_dismissed'    => array(
				'initial_wp_pointers'   => '',
				'hook_suffix'           => 'index.php',
				'expected'              => true,
				'dismissed_wp_pointers' => 'perflab-feature-view-transitions',
			),
			'plugins_not_dismissed'      => array(
				'initial_wp_pointers'   => '',
				'hook_suffix'           => 'plugins.php',
				'expected'              => true,
				'dismissed_wp_pointers' => 'perflab-feature-view-transitions',
			),
			'dashboard_new_dismissed'    => array(
				// Note: If the No-cache BFCache plugin (not part of the monorepo) is installed, then this test will likely fail and it should be skipped.
				'initial_wp_pointers'   => 'perflab-admin-pointer',
				'hook_suffix'           => 'index.php',
				'expected'              => true,
				'dismissed_wp_pointers' => 'perflab-admin-pointer,perflab-feature-view-transitions',
			),
			'dashboard_one_dismissed'    => array(
				// Note: The No-cache BFCache plugin is not part of the monorepo, so it is not automatically installed in the dev environment.
				'initial_wp_pointers'   => 'perflab-admin-pointer,perflab-feature-nocache-bfcache',
				'hook_suffix'           => 'index.php',
				'expected'              => false,
				'dismissed_wp_pointers' => 'perflab-admin-pointer,perflab-feature-nocache-bfcache,perflab-feature-view-transitions',
			),
			'dashboard_all_dismissed'    => array(
				'initial_wp_pointers'   => implode( ',', array_keys( perflab_get_admin_pointers() ) ),
				'hook_suffix'           => 'index.php',
				'expected'              => false,
				'dismissed_wp_pointers' => implode( ',', array_keys( perflab_get_admin_pointers() ) ),
			),
			'perflab_screen_first_time'  => array(
				'initial_wp_pointers'   => '',
				'hook_suffix'           => 'settings_page_' . PERFLAB_SCREEN,
				'expected'              => false,
				'dismissed_wp_pointers' => implode( ',', array_keys( perflab_get_admin_pointers() ) ),
			),
			'perflab_screen_second_time' => array(
				'initial_wp_pointers'   => 'perflab-admin-pointer',
				'hook_suffix'           => 'settings_page_' . PERFLAB_SCREEN,
				'expected'              => false,
				'dismissed_wp_pointers' => implode( ',', array_keys( perflab_get_admin_pointers() ) ),
			),
		);
	}

	/**
	 * @covers ::perflab_admin_pointer
	 * @dataProvider data_provider_test_perflab_admin_pointer
	 *
	 * @param string      $initial_wp_pointers   Set up.
	 * @param string|null $hook_suffix           Hook suffix.
	 * @param bool        $expected              Expected.
	 * @param string      $dismissed_wp_pointers Dismissed admin pointers.
	 */
	public function test_perflab_admin_pointer( string $initial_wp_pointers, ?string $hook_suffix, bool $expected, string $dismissed_wp_pointers ): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		update_user_meta( wp_get_current_user()->ID, 'dismissed_wp_pointers', $initial_wp_pointers );
		$this->assertFalse( is_network_admin() || is_user_admin() );
		perflab_admin_pointer( $hook_suffix );

		$after_script      = '';
		$script_dependency = wp_scripts()->query( 'wp-pointer' );
		if ( $script_dependency instanceof _WP_Dependency ) {
			$after_script = implode( "\n", array_filter( $script_dependency->extra['after'] ?? array() ) );
		}
		if ( $expected ) {
			$this->assertStringContainsString( 'pointerIdsToDismiss', $after_script );
		} else {
			$this->assertStringNotContainsString( 'pointerIdsToDismiss', $after_script );
		}
		$this->assertSame( $expected, wp_script_is( 'wp-pointer', 'enqueued' ) );
		$this->assertSame( $expected, wp_style_is( 'wp-pointer', 'enqueued' ) );
		$this->assertSame( $dismissed_wp_pointers, get_user_meta( $user_id, 'dismissed_wp_pointers', true ) );
	}

	/**
	 * @covers ::perflab_plugin_action_links_add_settings
	 */
	public function test_perflab_plugin_action_links_add_settings(): void {
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

	/**
	 * @return array<int, mixed>
	 */
	public function data_provider_to_test_perflab_sanitize_plugin_slug(): array {
		return array(
			array(
				'webp-uploads',
				'webp-uploads',
			),
			array(
				'akismet',
				null,
			),
			array(
				1,
				null,
			),
			array(
				array( 'speculative-loading' ),
				null,
			),
		);
	}

	/**
	 * @covers ::perflab_sanitize_plugin_slug
	 *
	 * @dataProvider data_provider_to_test_perflab_sanitize_plugin_slug
	 *
	 * @param mixed       $slug     Slug.
	 * @param string|null $expected Expected.
	 */
	public function test_perflab_sanitize_plugin_slug( $slug, ?string $expected ): void {
		$this->assertSame( $expected, perflab_sanitize_plugin_slug( $slug ) );
	}
}
