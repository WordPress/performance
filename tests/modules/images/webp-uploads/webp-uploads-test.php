<?php
/**
 * Tests for webp-uploads module.
 *
 * @package performance-lab
 */

class WebP_Uploads_Tests extends WP_UnitTestCase {

	/**
	 * Test if webp-uploads applies filter based on system support of WebP.
	 */
	function test_webp_uploads_applies_filter_based_on_system_support() {
		// Set the expected value based on the underlying system support for WebP.
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$expect = array();
		} else {
			$expect = array( 'image/jpeg' => 'image/webp' );
		}

		$output_format = apply_filters( 'image_editor_output_format', array(), '', 'image/jpeg' );
		$this->assertEquals( $expect, $output_format );
	}

	/**
	 * Verify webp-uploads applies filter with a system that supports WebP.
	 */
	function test_webp_uploads_filter_image_editor_output_format_with_support() {
		// Mock a system that supports WebP.
		add_filter( 'wp_image_editors', array( $this, 'mock_wp_image_editor_supports' ) );
		$output_format = webp_uploads_filter_image_editor_output_format( array(), '', 'image/jpeg' );
		$this->assertEquals( array( 'image/jpeg' => 'image/webp' ), $output_format );
	}

	/**
	 * Verify if webp-uploads doesn't apply filter with a system that doesn't support WebP.
	 */
	function test_webp_uploads_filter_image_editor_output_format_without_support() {
		// Mock a system that doesn't support WebP.
		add_filter( 'wp_image_editors', array( $this, 'mock_wp_image_editor_doesnt_support' ) );
		$output_format = webp_uploads_filter_image_editor_output_format( array(), '', 'image/jpeg' );
		$this->assertEquals( array(), $output_format );
	}

	/**
	 * Mock an image editor that supports WebP.
	 */
	public function mock_wp_image_editor_supports() {
		$editors = array( 'WP_Image_Supports_WebP' );
		return $editors;
	}

	/**
	 * Mock an image editor that doesn't support WebP.
	 */
	public function mock_wp_image_editor_doesnt_support() {
		$editors = array( 'WP_Image_Doesnt_Support_WebP' );
		return $editors;
	}
}
