<?php
/**
 * Tests for image-loading-optimization module optimization.php.
 *
 * @package performance-lab
 * @group   image-loading-optimization
 */

class Image_Loading_Optimization_Optimization_Tests extends WP_UnitTestCase {

	/**
	 * Test ilo_maybe_add_template_output_buffer_filter().
	 *
	 * @test
	 * @covers ::ilo_maybe_add_template_output_buffer_filter
	 */
	public function test_ilo_maybe_add_template_output_buffer_filter() {
		$this->assertFalse( has_filter( 'ilo_template_output_buffer', 'ilo_optimize_template_output_buffer' ) );

		add_filter( 'ilo_can_optimize_response', '__return_false', 1 );
		ilo_maybe_add_template_output_buffer_filter();
		$this->assertFalse( ilo_can_optimize_response() );
		$this->assertFalse( has_filter( 'ilo_template_output_buffer', 'ilo_optimize_template_output_buffer' ) );

		add_filter( 'ilo_can_optimize_response', '__return_true', 2 );
		$this->go_to( home_url( '/' ) );
		$this->assertTrue( ilo_can_optimize_response() );
		ilo_maybe_add_template_output_buffer_filter();
		$this->assertSame( 10, has_filter( 'ilo_template_output_buffer', 'ilo_optimize_template_output_buffer' ) );
	}

	/**
	 * Test ilo_construct_preload_links().
	 *
	 * @test
	 * @covers ::ilo_construct_preload_links
	 */
	public function test_ilo_construct_preload_links() {
		$this->markTestIncomplete();
	}

	/**
	 * Test ilo_optimize_template_output_buffer().
	 *
	 * @test
	 * @covers ::ilo_optimize_template_output_buffer
	 */
	public function test_ilo_optimize_template_output_buffer() {
		$this->markTestIncomplete();
	}
}
