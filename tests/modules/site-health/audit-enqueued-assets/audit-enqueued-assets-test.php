<?php
/**
 * Tests for audit-enqueued-assets module.
 *
 * @package performance-lab
 */

class Audit_Enqueued_Assets_Tests extends WP_UnitTestCase {

	/**
	 * Asset limit before giving warning.
	 */
	const WARNING_ASSETS_LIMIT = 11;

	/**
	 * Tests perflab_aea_audit_enqueued_scripts() when transient is already set.
	 */
	public function test_perflab_aea_audit_enqueued_scripts_transient_already_set() {
		Audit_Assets_Transients_Set::set_script_transient_with_data( 3 );
		perflab_aea_audit_enqueued_scripts();
		$transient = get_transient( 'aea_enqueued_scripts' );
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
		wp_enqueue_script( 'script1', 'example1.com', array() );
		wp_enqueue_script( 'script_to_be_discarded', '/wp-includes/example2.com', array() );
		wp_enqueue_script( 'script3', 'example3.com', array() );
		wp_dequeue_script( 'script3' );

		perflab_aea_audit_enqueued_scripts();
		$transient = get_transient( 'aea_enqueued_scripts' );
		$this->assertNotEmpty( $transient );
		$this->assertEquals( 1, count( $transient ) );
		$this->assertEqualSets(
			array(
				array(
					'src'  => 'example1.com',
					'size' => 0,
				),
			),
			$transient
		);

	}

	/**
	 * Tests perflab_aea_audit_enqueued_styles() when transient is already set.
	 */
	public function test_perflab_aea_audit_enqueued_styles_transient_already_set() {
		Audit_Assets_Transients_Set::set_style_transient_with_data( 3 );
		perflab_aea_audit_enqueued_styles();
		$transient = get_transient( 'aea_enqueued_styles' );
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
		wp_enqueue_style( 'style1', 'example1.com', array() );
		wp_enqueue_style( 'style_to_be_discarded', '/wp-includes/example2.com', array() );
		wp_enqueue_style( 'style3', 'example3.com', array() );
		wp_dequeue_style( 'style3' );

		perflab_aea_audit_enqueued_styles();
		$transient = get_transient( 'aea_enqueued_styles' );
		$this->assertNotEmpty( $transient );
		$this->assertEquals( 1, count( $transient ) );
		$this->assertEqualSets(
			array(
				array(
					'src'  => 'example1.com',
					'size' => 0,
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
	 * Test perflab_aea_enqueued_js_assets_test() with data in transient ( less than WARNING_ASSETS_LIMIT ).
	 */
	public function test_perflab_aea_enqueued_js_assets_test_with_assets_less_than_limit() {
		Audit_Assets_Transients_Set::set_script_transient_with_data();
		$mocked_data = $this->mock_data_perflab_aea_enqueued_js_assets_test_callback();
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_js_assets_test() );
	}

	/**
	 * Test perflab_aea_enqueued_js_assets_test() with data in transient ( more than WARNING_ASSETS_LIMIT ).
	 */
	public function test_perflab_aea_enqueued_js_assets_test_with_assets_more_than_limit() {
		Audit_Assets_Transients_Set::set_script_transient_with_data( self::WARNING_ASSETS_LIMIT );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_js_assets_test_callback( self::WARNING_ASSETS_LIMIT );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_js_assets_test() );
	}

	/**
	 * Test perflab_aea_enqueued_css_assets_test() no transient saved.
	 */
	public function test_perflab_aea_enqueued_css_assets_test_no_transient() {
		$this->assertEqualSets( array(), perflab_aea_enqueued_css_assets_test() );
	}

	/**
	 * Test perflab_aea_enqueued_css_assets_test() with data in transient ( less than WARNING_ASSETS_LIMIT ).
	 */
	public function test_perflab_aea_enqueued_css_assets_test_with_assets_less_than_limit() {
		Audit_Assets_Transients_Set::set_style_transient_with_data();
		$mocked_data = $this->mock_data_perflab_aea_enqueued_css_assets_test_callback();
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_css_assets_test() );
	}

	/**
	 * Test perflab_aea_enqueued_css_assets_test() with data in transient ( more than WARNING_ASSETS_LIMIT ).
	 */
	public function test_aea_enqueued_cdd_assets_test_with_assets_more_than_limit() {
		Audit_Assets_Transients_Set::set_style_transient_with_data( self::WARNING_ASSETS_LIMIT );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_css_assets_test_callback( self::WARNING_ASSETS_LIMIT );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_css_assets_test() );
	}

	/**
	 * Tests perflab_invalidate_cache_transients() functionality.
	 */
	public function test_perflab_invalidate_cache_transients() {
		Audit_Assets_Transients_Set::set_script_transient_with_data();
		Audit_Assets_Transients_Set::set_style_transient_with_data();
		perflab_invalidate_cache_transients();
		$this->assertFalse( get_transient( 'aea_enqueued_scripts' ) );
		$this->assertFalse( get_transient( 'aea_enqueued_styles' ) );
	}

	/**
	 * Tests perflab_clean_aea_audit_action() functionality.
	 */
	public function test_perflab_clean_aea_audit_action() {
		Audit_Assets_Transients_Set::set_script_transient_with_data();
		Audit_Assets_Transients_Set::set_style_transient_with_data();
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'clean_aea_audit' );
		$_GET['action']       = 'clean_aea_audit';
		perflab_clean_aea_audit_action();
		$this->assertFalse( get_transient( 'aea_enqueued_scripts' ) );
		$this->assertFalse( get_transient( 'aea_enqueued_styles' ) );
	}

	/**
	 * @param int $number_of_assets Number of assets mocked.
	 *
	 * @return array
	 */
	public function mock_data_perflab_aea_enqueued_js_assets_test_callback( $number_of_assets = 5 ) {
		if ( $number_of_assets < self::WARNING_ASSETS_LIMIT ) {
			return Site_Health_Mock_Responses::return_aea_enqueued_js_assets_test_callback_less_than_limit();
		}
		return Site_Health_Mock_Responses::return_aea_enqueued_js_assets_test_callback_more_than_limit( $number_of_assets );
	}

	/**
	 * @param int $number_of_assets Number of styles mocked.
	 *
	 * @return array
	 */
	public function mock_data_perflab_aea_enqueued_css_assets_test_callback( $number_of_assets = 5 ) {
		if ( $number_of_assets < self::WARNING_ASSETS_LIMIT ) {
			return Site_Health_Mock_Responses::return_aea_enqueued_css_assets_test_callback_less_than_limit();
		}
		return Site_Health_Mock_Responses::return_aea_enqueued_css_assets_test_callback_more_than_limit( $number_of_assets );
	}
}

