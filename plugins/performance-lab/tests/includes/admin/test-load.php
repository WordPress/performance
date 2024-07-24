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
	 * @return array<string, array{ hook_suffix: string|null, expected: bool }>
	 */
	public function data_provider_test_perflab_admin_pointer(): array {
		return array(
			'null'                       => array(
				'set_up'                => null,
				'hook_suffix'           => null,
				'expected'              => false,
				'assert'                => null,
				'dismissed_wp_pointers' => '',
			),
			'edit.php'                   => array(
				'set_up'                => null,
				'hook_suffix'           => 'edit.php',
				'expected'              => false,
				'assert'                => null,
				'dismissed_wp_pointers' => '',
			),
			'dashboard_not_dismissed'    => array(
				'set_up'                => null,
				'hook_suffix'           => 'index.php',
				'expected'              => true,
				'assert'                => null,
				'dismissed_wp_pointers' => '',
			),
			'plugins_not_dismissed'      => array(
				'set_up'                => null,
				'hook_suffix'           => 'plugins.php',
				'expected'              => true,
				'assert'                => null,
				'dismissed_wp_pointers' => '',
			),
			'dashboard_yes_dismissed'    => array(
				'set_up'                => static function (): void {
					update_user_meta( wp_get_current_user()->ID, 'dismissed_wp_pointers', 'perflab-admin-pointer' );
				},
				'hook_suffix'           => 'index.php',
				'expected'              => false,
				'assert'                => null,
				'dismissed_wp_pointers' => 'perflab-admin-pointer',
			),
			'perflab_screen_first_time'  => array(
				'set_up'                => static function (): void {
					$_GET['page'] = PERFLAB_SCREEN;
				},
				'hook_suffix'           => 'options-general.php',
				'expected'              => false,
				'assert'                => null,
				'dismissed_wp_pointers' => 'perflab-admin-pointer',
			),
			'perflab_screen_second_time' => array(
				'set_up'                => static function (): void {
					$_GET['page'] = PERFLAB_SCREEN;
					update_user_meta( wp_get_current_user()->ID, 'dismissed_wp_pointers', 'perflab-admin-pointer' );
				},
				'hook_suffix'           => 'options-general.php',
				'expected'              => false,
				'assert'                => null,
				'dismissed_wp_pointers' => 'perflab-admin-pointer',
			),
		);
	}

	/**
	 * @covers ::perflab_admin_pointer
	 * @dataProvider data_provider_test_perflab_admin_pointer
	 *
	 * @param Closure|null $set_up      Set up.
	 * @param string|null  $hook_suffix Hook suffix.
	 * @param bool         $expected    Expected.
	 * @param Closure|null $assert      Assert.
	 * @param string       $dismissed_wp_pointers Dismissed admin pointers.
	 */
	public function test_perflab_admin_pointer( ?Closure $set_up, ?string $hook_suffix, bool $expected, ?Closure $assert, string $dismissed_wp_pointers ): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		if ( $set_up instanceof Closure ) {
			$set_up();
		}
		$this->assertFalse( is_network_admin() || is_user_admin() );
		perflab_admin_pointer( $hook_suffix );
		$this->assertSame( $expected ? 10 : false, has_action( 'admin_print_footer_scripts', 'perflab_render_pointer' ) );
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
