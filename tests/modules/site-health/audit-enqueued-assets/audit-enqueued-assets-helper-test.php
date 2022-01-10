<?php
/**
 * Tests for audit-enqueued-assets helper file.
 *
 * @package performance-lab
 */

class Audit_Enqueued_Assets_Helper_Tests extends WP_UnitTestCase {

	/**
	 * Tests perflab_aea_get_total_enqueued_scripts() no transient saved.
	 */
	public function test_perflab_aea_get_total_enqueued_scripts_no_transient() {
		$total_enqueued_scripts = perflab_aea_get_total_enqueued_scripts();
		$this->assertFalse( $total_enqueued_scripts );
	}

	/**
	 * Tests perflab_aea_get_total_enqueued_scripts().
	 */
	public function test_perflab_aea_get_total_enqueued_scripts() {
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
	public function test_perflab_aea_get_total_size_bytes_enqueued_scripts() {
		$size_enqueued_scripts = perflab_aea_get_total_size_bytes_enqueued_scripts();
		$this->assertEquals( 0, $size_enqueued_scripts );

		Audit_Assets_Transients_Set::set_script_transient_with_data( 5 );
		$total_enqueued_scripts = perflab_aea_get_total_size_bytes_enqueued_scripts();
		$this->assertEquals( 5000, $total_enqueued_scripts );
	}

	/**
	 * Tests perflab_aea_get_total_enqueued_styles() with transient saved.
	 */
	public function test_perflab_aea_get_total_enqueued_styles() {
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
	public function test_perflab_aea_get_total_size_bytes_enqueued_styles() {
		$size_enqueued_scripts = perflab_aea_get_total_size_bytes_enqueued_styles();
		$this->assertEquals( 0, $size_enqueued_scripts );

		Audit_Assets_Transients_Set::set_style_transient_with_data( 5 );
		$total_enqueued_styles = perflab_aea_get_total_size_bytes_enqueued_styles();
		$this->assertEquals( 5000, $total_enqueued_styles );
	}

	/**
	 * Tests perflab_aea_get_path_from_resource_url() functionality.
	 */
	public function test_perflab_aea_get_path_from_resource_url() {
		$test_url      = 'https://example.com/wp-content/themes/test-theme/style.css';
		$expected_path = ABSPATH . '/wp-content/themes/test-theme/style.css';
		$this->assertSame( $expected_path, perflab_aea_get_path_from_resource_url( $test_url ) );
	}

	/**
	 * Tests perflab_aea_get_resource_file_size() functionality.
	 */
	public function test_perflab_aea_get_resource_file_size() {
		$non_existing_resource = ABSPATH . '/wp-content/themes/test-theme/style.css';
		$this->assertEquals( 0, perflab_aea_get_resource_file_size( $non_existing_resource ) );

		// Upload a fake resource file.
		$filename = __FUNCTION__ . '.css';
		$contents = __FUNCTION__ . '_contents';
		$file     = wp_upload_bits( $filename, null, $contents );
		$this->assertEquals( filesize( $file['file'] ), perflab_aea_get_resource_file_size( $file['file'] ) );
	}

}

