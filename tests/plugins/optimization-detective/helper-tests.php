<?php
/**
 * Tests for optimization-detective plugin helper.php.
 *
 * @package optimization-detective
 */

class OD_Helper_Tests extends WP_UnitTestCase {

	/**
	 * Test printing the meta generator tag.
	 *
	 * @covers ::od_render_generator_meta_tag
	 */
	public function test_od_render_generator_meta_tag() {
		$tag = get_echo( 'od_render_generator_meta_tag' );
		$this->assertStringStartsWith( '<meta', $tag );
		$this->assertStringContainsString( 'generator', $tag );
		$this->assertStringContainsString( 'Optimization Detective ' . OPTIMIZATION_DETECTIVE_VERSION, $tag );
	}
}
