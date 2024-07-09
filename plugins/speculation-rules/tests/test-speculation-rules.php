<?php
/**
 * Tests for speculation-rules plugin.
 *
 * @package speculation-rules
 */

class Test_Speculation_Rules extends WP_UnitTestCase {

	/** @var array<string, mixed> */
	private $original_wp_theme_features = array();

	public function set_up(): void {
		parent::set_up();
		$this->original_wp_theme_features = $GLOBALS['_wp_theme_features'];
	}

	public function tear_down(): void {
		$GLOBALS['_wp_theme_features'] = $this->original_wp_theme_features;
		parent::tear_down();
	}

	public function test_hooks(): void {
		$this->assertSame( 10, has_action( 'wp_footer', 'plsr_print_speculation_rules' ) );
		$this->assertSame( 10, has_action( 'wp_head', 'plsr_render_generator_meta_tag' ) );
	}

	/**
	 * @covers ::plsr_print_speculation_rules
	 */
	public function test_plsr_print_speculation_rules_without_html5_support(): void {
		$output = get_echo( 'plsr_print_speculation_rules' );
		$this->assertStringContainsString( '<script type="speculationrules">', $output );

		$json  = str_replace( array( '<script type="speculationrules">', '</script>' ), '', $output );
		$rules = json_decode( $json, true );
		$this->assertIsArray( $rules );
		$this->assertArrayHasKey( 'prerender', $rules );
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
