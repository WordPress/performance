<?php
/**
 * Tests for audit-enqueued-assets module.
 *
 * @package performance-lab
 */

class Audit_Enqueued_Assets_Tests extends WP_UnitTestCase {

	/**
	 * Tests aea_get_total_enqueued_scripts() no data.
	 */
	function test_aea_get_total_enqueued_scripts_no_transient() {
		$this->mock_set_script_transient_with_no_data();
		$total_enqueued_scripts = aea_get_total_enqueued_scripts();
		$this->assertFalse( $total_enqueued_scripts );
	}

	/**
	 * Tests aea_get_total_enqueued_scripts() with data.
	 */
	function test_aea_get_total_enqueued_scripts_transient() {
		$this->mock_set_script_transient_with_data();
		$total_enqueued_scripts = aea_get_total_enqueued_scripts();
		$this->assertIsInt( $total_enqueued_scripts );
	}

	/**
	 * Tests aea_get_total_enqueued_styles() no data.
	 */
	function test_aea_get_total_enqueued_styles_no_transient() {
		$this->mock_set_style_transient_with_no_data();
		$total_enqueued_styles = aea_get_total_enqueued_styles();
		$this->assertFalse( $total_enqueued_styles );
	}

	/**
	 * Tests mock_set_style_transient_with_data() with data.
	 */
	function test_aea_get_total_enqueued_styles_transient() {
		$this->mock_set_style_transient_with_data();
		$total_enqueued_styles = aea_get_total_enqueued_styles();
		$this->assertIsInt( $total_enqueued_styles );
	}

	/**
	 * Mocks Script transient with data.
	 */
	public function mock_set_script_transient_with_data() {
		Audit_Assets_Transients_Set::set_script_transient_with_data();
	}

	/**
	 * Mocks Script transient non existing.
	 */
	public function mock_set_script_transient_with_no_data() {
		Audit_Assets_Transients_Set::set_script_transient_with_no_data();
	}

	/**
	 * Mocks Styles transient with data.
	 */
	public function mock_set_style_transient_with_data() {
		Audit_Assets_Transients_Set::set_style_transient_with_data();
	}

	/**
	 * Mocks Styles transient non existing.
	 */
	public function mock_set_style_transient_with_no_data() {
		Audit_Assets_Transients_Set::set_style_transient_with_no_data();
	}
}

