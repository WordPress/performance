<?php
/**
 * Tests for optimization-detective plugin hooks.php.
 *
 * @package optimization-detective
 */

class OD_Hooks_Tests extends WP_UnitTestCase {

	/**
	 * Make sure the hooks are added in hooks.php.
	 *
	 * @see OD_Storage_Post_Type_Tests::test_add_hooks()
	 */
	public function test_hooks_added(): void {
		$this->assertEquals( PHP_INT_MAX, has_filter( 'template_include', 'od_buffer_output' ) );
		$this->assertEquals( 10, has_filter( 'wp', 'od_maybe_add_template_output_buffer_filter' ) );
		$this->assertSame(
			10,
			has_action(
				'init',
				array(
					OD_URL_Metrics_Post_Type::class,
					'register_post_type',
				)
			)
		);
		$this->assertEquals( 10, has_action( 'wp_head', 'od_render_generator_meta_tag' ) );
	}
}
