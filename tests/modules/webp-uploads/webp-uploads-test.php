<?php
/**
 * Tests for webp-uploads module.
 *
 * @package performance-lab
 */

class WebP_Uploads_Tests extends WP_UnitTestCase {

	/**
	 * Test if webp-uploads is loaded and filter applied when system supports WebP.
	 */
	function test_webp_uploads_is_loaded() {
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$expect = array();
		} else {
			$expect = array(
				'image/jpeg' => 'image/webp',
			);
		}

		$output_format = apply_filters( 'image_editor_output_format', array(), '', 'image/jpeg' );
		$this->assertEquals( $expect, $output_format );
	}
}
