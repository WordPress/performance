<?php
/**
 * Tests for audit-enqueued-assets helper file.
 *
 * @package performance-lab
 * @group audit-enqueued-assets
 */

class Test_Audit_Enqueued_Assets_Helper extends WP_UnitTestCase {

	/**
	 * Tests perflab_aea_get_total_enqueued_scripts() no transient saved.
	 */
	public function test_perflab_aea_get_total_enqueued_scripts_no_transient(): void {
		$total_enqueued_scripts = perflab_aea_get_total_enqueued_scripts();
		$this->assertFalse( $total_enqueued_scripts );
	}

	/**
	 * Tests perflab_aea_get_total_enqueued_scripts().
	 */
	public function test_perflab_aea_get_total_enqueued_scripts(): void {
		$total_enqueued_styles = perflab_aea_get_total_enqueued_styles();
		$this->assertFalse( $total_enqueued_styles );

		Audit_Assets_Transients_Set::set_script_transient_with_data( 5 );
		$total_enqueued_scripts = perflab_aea_get_total_enqueued_scripts();
		$this->assertIsInt( $total_enqueued_scripts );
		$this->assertEquals( 5, $total_enqueued_scripts );
	}

	/**
	 * Tests perflab_aea_get_total_size_bytes_enqueued_scripts().
	 */
	public function test_perflab_aea_get_total_size_bytes_enqueued_scripts(): void {
		$size_enqueued_scripts = perflab_aea_get_total_size_bytes_enqueued_scripts();
		$this->assertFalse( $size_enqueued_scripts );

		Audit_Assets_Transients_Set::set_script_transient_with_data( 5 );
		$total_enqueued_scripts = perflab_aea_get_total_size_bytes_enqueued_scripts();
		$this->assertEquals( 5000, $total_enqueued_scripts );
	}

	/**
	 * Tests perflab_aea_get_total_enqueued_styles() with transient saved.
	 */
	public function test_perflab_aea_get_total_enqueued_styles(): void {
		$total_enqueued_styles = perflab_aea_get_total_enqueued_styles();
		$this->assertEquals( 0, $total_enqueued_styles );

		Audit_Assets_Transients_Set::set_style_transient_with_data( 5 );
		$total_enqueued_styles = perflab_aea_get_total_enqueued_styles();
		$this->assertIsInt( $total_enqueued_styles );
		$this->assertEquals( 5, $total_enqueued_styles );
	}

	/**
	 * Tests perflab_aea_get_total_size_bytes_enqueued_styles().
	 */
	public function test_perflab_aea_get_total_size_bytes_enqueued_styles(): void {
		$size_enqueued_scripts = perflab_aea_get_total_size_bytes_enqueued_styles();
		$this->assertFalse( $size_enqueued_scripts );

		Audit_Assets_Transients_Set::set_style_transient_with_data( 5 );
		$total_enqueued_styles = perflab_aea_get_total_size_bytes_enqueued_styles();
		$this->assertEquals( 5000, $total_enqueued_styles );
	}
}
