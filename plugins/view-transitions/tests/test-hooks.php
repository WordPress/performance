<?php
/**
 * Tests for the View Transitions plugin hooks.php file.
 *
 * @package view-transitions
 * @group   view-transitions
 */

class Test_ViewTransitions_Hooks extends WP_UnitTestCase {

	public function test_hooks(): void {
		$this->assertSame( 10, has_action( 'wp_head', 'plvt_render_generator' ) );
		$this->assertSame( PHP_INT_MAX, has_action( 'after_setup_theme', 'plvt_polyfill_theme_support' ) );
		$this->assertSame( 1, has_action( 'init', 'plvt_sanitize_view_transitions_theme_support' ) );
		$this->assertSame( 10, has_action( 'wp_enqueue_scripts', 'plvt_load_view_transitions' ) );
	}

	/**
	 * @covers ::plvt_render_generator
	 */
	public function test_plvt_render_generator(): void {
		$expected = '<meta name="generator" content="view-transitions ' . VIEW_TRANSITIONS_VERSION . '">' . "\n";
		$output   = get_echo( 'plvt_render_generator' );
		$this->assertSame( $expected, $output );
	}
}
