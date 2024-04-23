<?php
/**
 * Tests for optimization-detective plugin helper.php.
 *
 * @package optimization-detective
 */

class Test_IP_Helper extends WP_UnitTestCase {

	/**
	 * Test printing the meta generator tag.
	 *
	 * @covers ::ip_render_generator_meta_tag
	 */
	public function test_ip_render_generator_meta_tag(): void {
		$function_name = 'ip_render_generator_meta_tag';
		$this->assertSame( 10, has_action( 'wp_head', $function_name ) );
		$tag = get_echo( $function_name );
		$this->assertStringStartsWith( '<meta', $tag );
		$this->assertStringContainsString( 'generator', $tag );
		$this->assertStringContainsString( 'image-prioritizer ' . IMAGE_PRIORITIZER_VERSION, $tag );
	}
}
