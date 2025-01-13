<?php
/**
 * Tests for image-prioritizer plugin hooks.php.
 *
 * @package image-prioritizer
 */

class Test_Image_Prioritizer_Hooks extends WP_UnitTestCase {

	/**
	 * Make sure the hooks are added in hooks.php.
	 */
	public function test_hooks_added(): void {
		$this->assertEquals( 10, has_action( 'od_init', 'image_prioritizer_init' ) );
		$this->assertEquals( 10, has_filter( 'od_extension_module_urls', 'image_prioritizer_filter_extension_module_urls' ) );
		$this->assertEquals( 10, has_filter( 'od_url_metric_schema_root_additional_properties', 'image_prioritizer_add_root_schema_properties' ) );
		$this->assertEquals( 10, has_filter( 'rest_request_before_callbacks', 'image_prioritizer_filter_rest_request_before_callbacks' ) );
	}
}
