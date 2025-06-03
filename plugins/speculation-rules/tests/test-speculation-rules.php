<?php
/**
 * Tests for speculation-rules plugin.
 *
 * @package speculation-rules
 */

class Test_Speculation_Rules extends WP_UnitTestCase {

	public function test_hooks(): void {
		if ( function_exists( 'wp_get_speculation_rules_configuration' ) ) {
			$this->assertSame( 10, has_filter( 'wp_speculation_rules_configuration', 'plsr_filter_speculation_rules_configuration' ) );
			$this->assertSame( 10, has_filter( 'wp_speculation_rules_href_exclude_paths', 'plsr_filter_speculation_rules_exclude_paths' ) );
		} else {
			$this->assertSame( 10, has_action( 'wp_footer', 'plsr_print_speculation_rules' ) );
		}

		$this->assertSame( 10, has_action( 'wp_head', 'plsr_render_generator_meta_tag' ) );
	}

	/**
	 * Test printing the meta generator tag.
	 *
	 * @covers ::plsr_render_generator_meta_tag
	 */
	public function test_plsr_render_generator_meta_tag(): void {
		$tag = get_echo( 'plsr_render_generator_meta_tag' );
		$this->assertStringStartsWith( '<meta', $tag );
		$this->assertStringContainsString( 'generator', $tag );
		$this->assertStringContainsString( 'speculation-rules ' . SPECULATION_RULES_VERSION, $tag );
	}
}
