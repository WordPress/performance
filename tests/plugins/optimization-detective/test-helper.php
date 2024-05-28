<?php
/**
 * Tests for optimization-detective plugin helper.php.
 *
 * @package optimization-detective
 */

class Test_OD_Helper extends WP_UnitTestCase {

	/**
	 * Test printing the meta generator tag.
	 *
	 * @covers ::od_render_generator_meta_tag
	 */
	public function test_od_render_generator_meta_tag(): void {
		$tag = get_echo( 'od_render_generator_meta_tag' );
		$this->assertStringStartsWith( '<meta', $tag );
		$this->assertStringContainsString( 'generator', $tag );
		$this->assertStringContainsString( 'optimization-detective ' . OPTIMIZATION_DETECTIVE_VERSION, $tag );
	}
}
