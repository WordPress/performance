<?php
/**
 * Tests for audit-enqueued-assets helper file.
 *
 * @package performance-lab
 * @group audit-enqueued-assets
 */

class Audit_Enqueued_Assets_Helper_Tests extends WP_UnitTestCase {

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

	/**
	 * Tests perflab_aea_get_path_from_resource_url() functionality.
	 */
	public function test_perflab_aea_get_path_from_resource_url(): void {
		$test_url      = site_url() . '/wp-content/themes/test-theme/style.css';
		$expected_path = ABSPATH . 'wp-content/themes/test-theme/style.css';
		$this->assertSame( $expected_path, perflab_aea_get_path_from_resource_url( $test_url ) );
	}

	/**
	 * Tests perflab_aea_get_path_from_resource_url() functionality when wp-content in subdirectory.
	 */
	public function test_perflab_aea_get_path_from_resource_url_subdirectory(): void {
		$test_url      = site_url() . '/wp/wp-content/themes/test-theme/style.css';
		$expected_path = ABSPATH . 'wp/wp-content/themes/test-theme/style.css';
		$this->assertSame( $expected_path, perflab_aea_get_path_from_resource_url( $test_url ) );
	}

	/**
	 * Tests perflab_aea_get_path_from_resource_url() functionality empty $url.
	 */
	public function test_perflab_aea_get_path_from_resource_url_empty_url(): void {
		$test_url      = '';
		$expected_path = '';
		$this->assertSame( $expected_path, perflab_aea_get_path_from_resource_url( $test_url ) );
	}

	/**
	 * Tests perflab_aea_get_path_from_resource_url() functionality when wp-content outside wp directory.
	 */
	public function test_perflab_aea_get_path_from_resource_url_outside_wp_setup(): void {
		$test_url      = site_url() . '/content/themes/test-theme/style.css';
		$expected_path = WP_CONTENT_DIR . '/themes/test-theme/style.css';
		add_filter(
			'content_url',
			static function () {
				return site_url() . '/content';
			}
		);
		$this->assertSame( $expected_path, perflab_aea_get_path_from_resource_url( $test_url ) );
	}
}
