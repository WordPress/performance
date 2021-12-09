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
	 * Tests aea_get_total_enqueued_scripts() no data.
	 */
	public function test_aea_get_total_enqueued_scripts_no_transient() {
		$this->mock_set_script_transient_with_no_data();
		$total_enqueued_scripts = aea_get_total_enqueued_scripts();
		$this->assertFalse( $total_enqueued_scripts );
	}

	/**
	 * Tests aea_get_total_enqueued_scripts() with data.
	 */
	public function test_aea_get_total_enqueued_scripts_transient() {
		$this->mock_set_script_transient_with_data();
		$total_enqueued_scripts = aea_get_total_enqueued_scripts();
		$this->assertIsInt( $total_enqueued_scripts );
	}

	/**
	 * Tests aea_get_total_enqueued_styles() no data.
	 */
	public function test_aea_get_total_enqueued_styles_no_transient() {
		$this->mock_set_style_transient_with_no_data();
		$total_enqueued_styles = aea_get_total_enqueued_styles();
		$this->assertFalse( $total_enqueued_styles );
	}

	/**
	 * Tests mock_set_style_transient_with_data() with data.
	 */
	public function test_aea_get_total_enqueued_styles_transient() {
		$this->mock_set_style_transient_with_data();
		$total_enqueued_styles = aea_get_total_enqueued_styles();
		$this->assertIsInt( $total_enqueued_styles );
	}

	/**
	 * Make sure aea_add_enqueued_assets_test adds the right information.
	 */
	public function test_aea_add_enqueued_assets_test() {
		$this->assertIsArray( aea_add_enqueued_assets_test( array() ) );
		$this->assertEqualSets(
			aea_add_enqueued_assets_test( array() ),
			$this->mock_data_aea_add_enqueued_assets_test()
		);
	}

	/**
	 * Test aea_enqueued_js_assets_test() no data in transient set.
	 */
	public function test_aea_enqueued_js_assets_test_no_transient() {
		$this->mock_set_script_transient_with_no_data();
		$this->assertEqualSets( array(), aea_enqueued_js_assets_test() );
	}

	/**
	 * Test aea_enqueued_js_assets_test() with data in transient ( less than WARNING_ASSETS_LIMIT ).
	 */
	public function test_aea_enqueued_js_assets_test_with_assets_less_than_limit() {
		$this->mock_set_script_transient_with_data();
		$mocked_data = $this->mock_data_aea_enqueued_js_assets_test_callback();
		$this->assertEqualSets( $mocked_data, aea_enqueued_js_assets_test() );
	}

	/**
	 * Test aea_enqueued_js_assets_test() with data in transient ( more than WARNING_ASSETS_LIMIT ).
	 */
	public function test_aea_enqueued_js_assets_test_with_assets_more_than_limit() {
		$this->mock_set_script_transient_with_data( self::WARNING_ASSETS_LIMIT );
		$mocked_data = $this->mock_data_aea_enqueued_js_assets_test_callback( self::WARNING_ASSETS_LIMIT );
		$this->assertEqualSets( $mocked_data, aea_enqueued_js_assets_test() );
	}

	/**
	 * Test aea_enqueued_js_assets_test() no data in transient set.
	 */
	public function test_aea_enqueued_css_assets_test_no_transient() {
		$this->mock_set_style_transient_with_no_data();
		$this->assertEqualSets( array(), aea_enqueued_css_assets_test() );
	}

	/**
	 * Test aea_enqueued_css_assets_test() with data in transient ( less than WARNING_ASSETS_LIMIT ).
	 */
	public function test_aea_enqueued_css_assets_test_with_assets_less_than_limit() {
		$this->mock_set_style_transient_with_data();
		$mocked_data = $this->mock_data_aea_enqueued_css_assets_test_callback();
		$this->assertEqualSets( $mocked_data, aea_enqueued_css_assets_test() );
	}

	/**
	 * Test aea_enqueued_css_assets_test() with data in transient ( more than WARNING_ASSETS_LIMIT ).
	 */
	public function test_aea_enqueued_cdd_assets_test_with_assets_more_than_limit() {
		$this->mock_set_style_transient_with_data( self::WARNING_ASSETS_LIMIT );
		$mocked_data = $this->mock_data_aea_enqueued_css_assets_test_callback( self::WARNING_ASSETS_LIMIT );
		$this->assertEqualSets( $mocked_data, aea_enqueued_css_assets_test() );
	}

	/**
	 * Mocks Script transient with data.
	 *
	 * @param int $number_of_assets Number of assets to mock.
	 */
	public function mock_set_script_transient_with_data( $number_of_assets = 5 ) {
		Audit_Assets_Transients_Set::set_script_transient_with_data( $number_of_assets );
	}

	/**
	 * Mocks Script transient non-existing.
	 */
	public function mock_set_script_transient_with_no_data() {
		Audit_Assets_Transients_Set::set_script_transient_with_no_data();
	}

	/**
	 * Mocks Styles transient with data.
	 *
	 * @param int $number_of_assets Number of assets to mock.
	 */
	public function mock_set_style_transient_with_data( $number_of_assets = 5 ) {
		Audit_Assets_Transients_Set::set_style_transient_with_data( $number_of_assets );
	}

	/**
	 * Mocks Styles transient non-existing.
	 */
	public function mock_set_style_transient_with_no_data() {
		Audit_Assets_Transients_Set::set_style_transient_with_no_data();
	}

	/**
	 * Returns information added by aea_add_enqueued_assets_test.
	 *
	 * @return array
	 */
	public function mock_data_aea_add_enqueued_assets_test() {
		return Site_Health_Mock_Responses::return_added_test_info_site_health();
	}

	/**
	 * @param int $number_of_assets Number of assets mocked.
	 *
	 * @return array
	 */
	public function mock_data_aea_enqueued_js_assets_test_callback( $number_of_assets = 5 ) {
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
	public function mock_data_aea_enqueued_css_assets_test_callback( $number_of_assets = 5 ) {
		if ( $number_of_assets < self::WARNING_ASSETS_LIMIT ) {
			return Site_Health_Mock_Responses::return_aea_enqueued_css_assets_test_callback_less_than_limit();
		}
		return Site_Health_Mock_Responses::return_aea_enqueued_css_assets_test_callback_more_than_limit( $number_of_assets );
	}
}

