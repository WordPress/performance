<?php
/**
 * Tests for audit-enqueued-assets module.
 *
 * @package performance-lab
 * @group audit-enqueued-assets
 */

class Audit_Enqueued_Assets_Tests extends WP_UnitTestCase {

	const WARNING_SCRIPTS_THRESHOLD = 31;
	const WARNING_STYLES_THRESHOLD  = 11;

	/**
	 * Tests perflab_aea_audit_enqueued_scripts() when transient is already set.
	 */
	public function test_perflab_aea_audit_enqueued_scripts_transient_already_set() {
		/**
		 * Prepare scenario for test.
		 */
		$this->mock_is_front_page();
		$this->current_user_can_view_site_health_checks_cap();

		Audit_Assets_Transients_Set::set_script_transient_with_data( 3 );
		perflab_aea_audit_enqueued_scripts();
		$transient = get_transient( 'aea_enqueued_front_page_scripts' );
		$this->assertIsArray( $transient );
		$this->assertEquals( 3, count( $transient ) );
		$this->assertEqualSets(
			array(
				array(
					'src'  => 'script.js',
					'size' => 1000,
				),
				array(
					'src'  => 'script.js',
					'size' => 1000,
				),
				array(
					'src'  => 'script.js',
					'size' => 1000,
				),
			),
			$transient
		);
	}

	/**
	 * Tests perflab_aea_audit_enqueued_scripts() with no transient.
	 * Enqueued scripts ( not belonging to core /wp-includes/ ) will be saved in transient.
	 */
	public function test_perflab_aea_audit_enqueued_scripts() {
		/**
		 * Prepare scenario for test.
		 */
		$this->mock_is_front_page();
		$this->current_user_can_view_site_health_checks_cap();

		wp_enqueue_script( 'script1', 'example1.com', array() );
		wp_enqueue_script( 'script_to_be_discarded', '/wp-includes/example2.com', array() );
		wp_enqueue_script( 'script3', 'example3.com', array() );
		wp_dequeue_script( 'script3' );

		$inline_script = 'console.log("after");';
		wp_add_inline_script( 'script1', $inline_script );

		get_echo( 'wp_print_scripts' );
		perflab_aea_audit_enqueued_scripts();
		$transient = get_transient( 'aea_enqueued_front_page_scripts' );
		$this->assertNotEmpty( $transient );
		$this->assertEquals( 1, count( $transient ) );
		$this->assertEqualSets(
			array(
				array(
					'src'  => 'example1.com',
					'size' => 0 + mb_strlen( $inline_script, '8bit' ),
				),
			),
			$transient
		);

	}

	/**
	 * Tests perflab_aea_audit_enqueued_styles() when transient is already set.
	 */
	public function test_perflab_aea_audit_enqueued_styles_transient_already_set() {
		/**
		 * Prepare scenario for test.
		 */
		$this->mock_is_front_page();
		$this->current_user_can_view_site_health_checks_cap();

		Audit_Assets_Transients_Set::set_style_transient_with_data( 3 );

		// Avoid deprecation warning due to related change in WordPress 6.4.
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		get_echo( 'wp_print_styles' );

		perflab_aea_audit_enqueued_styles();
		$transient = get_transient( 'aea_enqueued_front_page_styles' );
		$this->assertIsArray( $transient );
		$this->assertEquals( 3, count( $transient ) );
		$this->assertEqualSets(
			array(
				array(
					'src'  => 'style.css',
					'size' => 1000,
				),
				array(
					'src'  => 'style.css',
					'size' => 1000,
				),
				array(
					'src'  => 'style.css',
					'size' => 1000,
				),
			),
			$transient
		);
	}

	/**
	 * Tests perflab_aea_audit_enqueued_styles() with no transient.
	 * Enqueued styles ( not belonging to core /wp-includes/ ) will be saved in transient.
	 */
	public function test_perflab_aea_audit_enqueued_styles() {
		/**
		 * Prepare scenario for test.
		 */
		$this->mock_is_front_page();
		$this->current_user_can_view_site_health_checks_cap();

		wp_enqueue_style( 'style1', 'example1.com', array() );
		wp_enqueue_style( 'style_to_be_discarded', '/wp-includes/example2.com', array() );
		wp_enqueue_style( 'style3', 'example3.com', array() );
		wp_dequeue_style( 'style3' );

		// Adding inline style to style1.
		$style  = ".test {\n";
		$style .= "\tbackground: red;\n";
		$style .= '}';
		wp_add_inline_style( 'style1', $style );

		// Avoid deprecation warning due to related change in WordPress 6.4.
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		get_echo( 'wp_print_styles' );

		perflab_aea_audit_enqueued_styles();
		$transient = get_transient( 'aea_enqueued_front_page_styles' );
		$this->assertNotEmpty( $transient );
		$this->assertEquals( 1, count( $transient ) );
		$this->assertEqualSets(
			array(
				array(
					'src'  => 'example1.com',
					'size' => 0 + mb_strlen( $style, '8bit' ),
				),
			),
			$transient
		);
	}

	/**
	 * Make sure perflab_aea_add_enqueued_assets_test adds the right information.
	 */
	public function test_perflab_aea_add_enqueued_assets_test() {
		$this->assertIsArray( perflab_aea_add_enqueued_assets_test( array() ) );
		$this->assertEqualSets(
			perflab_aea_add_enqueued_assets_test( array() ),
			Site_Health_Mock_Responses::return_added_test_info_site_health()
		);
	}

	/**
	 * Test perflab_aea_enqueued_js_assets_test() no transient saved.
	 */
	public function test_perflab_aea_enqueued_js_assets_test_no_transient() {
		$this->assertEqualSets( array(), perflab_aea_enqueued_js_assets_test() );
	}

	/**
	 * Test perflab_aea_enqueued_js_assets_test() with data in transient ( less than WARNING_SCRIPTS_threshold ).
	 */
	public function test_perflab_aea_enqueued_js_assets_test_with_assets_less_than_threshold() {
		Audit_Assets_Transients_Set::set_script_transient_with_data( 1 );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_js_assets_test_callback( 1 );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_js_assets_test() );
	}

	/**
	 * Test perflab_aea_enqueued_js_assets_test() with data in transient ( more than WARNING_SCRIPTS_threshold ).
	 */
	public function test_perflab_aea_enqueued_js_assets_test_with_assets_more_than_threshold() {
		Audit_Assets_Transients_Set::set_script_transient_with_data( self::WARNING_SCRIPTS_THRESHOLD );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_js_assets_test_callback( self::WARNING_SCRIPTS_THRESHOLD );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_js_assets_test() );
	}

	/**
	 * Test perflab_aea_enqueued_css_assets_test() no transient saved.
	 */
	public function test_perflab_aea_enqueued_css_assets_test_no_transient() {
		$this->assertEqualSets( array(), perflab_aea_enqueued_css_assets_test() );
	}

	/**
	 * Test perflab_aea_enqueued_css_assets_test() with data in transient ( less than WARNING_STYLES_threshold ).
	 */
	public function test_perflab_aea_enqueued_css_assets_test_with_assets_less_than_threshold() {
		Audit_Assets_Transients_Set::set_style_transient_with_data( 1 );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_css_assets_test_callback( 1 );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_css_assets_test() );
	}

	/**
	 * Test perflab_aea_enqueued_css_assets_test() with data in transient ( more than WARNING_STYLES_threshold ).
	 */
	public function test_aea_enqueued_cdd_assets_test_with_assets_more_than_threshold() {
		Audit_Assets_Transients_Set::set_style_transient_with_data( self::WARNING_STYLES_THRESHOLD );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_css_assets_test_callback( self::WARNING_STYLES_THRESHOLD );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_css_assets_test() );
	}

	/**
	 * Tests perflab_aea_invalidate_cache_transients() functionality.
	 */
	public function test_perflab_aea_invalidate_cache_transients() {
		Audit_Assets_Transients_Set::set_script_transient_with_data();
		Audit_Assets_Transients_Set::set_style_transient_with_data();
		perflab_aea_invalidate_cache_transients();
		$this->assertFalse( get_transient( 'aea_enqueued_front_page_scripts' ) );
		$this->assertFalse( get_transient( 'aea_enqueued_front_page_styles' ) );
	}

	/**
	 * Tests perflab_aea_clean_aea_audit_action() functionality.
	 */
	public function test_perflab_aea_clean_aea_audit_action() {
		Audit_Assets_Transients_Set::set_script_transient_with_data();
		Audit_Assets_Transients_Set::set_style_transient_with_data();
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'clean_aea_audit' );
		$_GET['action']       = 'clean_aea_audit';
		$this->current_user_can_view_site_health_checks_cap();
		perflab_aea_clean_aea_audit_action();
		$this->assertFalse( get_transient( 'aea_enqueued_front_page_scripts' ) );
		$this->assertFalse( get_transient( 'aea_enqueued_front_page_styles' ) );
	}

	/**
	 * Mock is_home in $wp_query.
	 */
	public function mock_is_front_page() {
		global $wp_query;
		$wp_query->is_home = true;
	}

	/**
	 * Adds view_site_health_checks capability to current user.
	 */
	public function current_user_can_view_site_health_checks_cap() {
		$current_user = wp_get_current_user();
		$current_user->add_cap( 'view_site_health_checks' );
	}

	/**
	 * @param int $number_of_assets Number of assets mocked.
	 *
	 * @return array
	 */
	public function mock_data_perflab_aea_enqueued_js_assets_test_callback( $number_of_assets = 5 ) {
		if ( $number_of_assets < self::WARNING_SCRIPTS_THRESHOLD ) {
			return Site_Health_Mock_Responses::return_aea_enqueued_js_assets_test_callback_less_than_threshold( $number_of_assets );
		}
		return Site_Health_Mock_Responses::return_aea_enqueued_js_assets_test_callback_more_than_threshold( $number_of_assets );
	}

	/**
	 * @param int $number_of_assets Number of styles mocked.
	 *
	 * @return array
	 */
	public function mock_data_perflab_aea_enqueued_css_assets_test_callback( $number_of_assets = 5 ) {
		if ( $number_of_assets < self::WARNING_STYLES_THRESHOLD ) {
			return Site_Health_Mock_Responses::return_aea_enqueued_css_assets_test_callback_less_than_threshold( $number_of_assets );
		}
		return Site_Health_Mock_Responses::return_aea_enqueued_css_assets_test_callback_more_than_threshold( $number_of_assets );
	}
}

