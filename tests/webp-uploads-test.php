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
		// Test before enabling.
		$output_format = apply_filters( 'image_editor_output_format', array(), '', 'image/jpeg' );
		$this->assertEquals( array(), $output_format );

		// Activate the module.
		$new_value = array(
			'webp-uploads' => array( 'enabled' => true ),
		);
		update_option( PERFLAB_MODULES_SETTING, $new_value );
		perflab_load_active_modules();

		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$expect = array();
		} else {
			$expect = array(
				'image/jpeg' => 'image/webp',
			);
		}

		$output_format = apply_filters( 'image_editor_output_format', array(), '', 'image/jpeg' );
		$this->assertEquals( $expect, $output_format );

		// Cleanup.
		update_option( PERFLAB_MODULES_SETTING, array() );
	}
}
