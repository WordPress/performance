<?php
/**
 * Tests for the improve sizes for Images.
 *
 * @package auto-sizes
 * @group   improve-sizes
 */

class Tests_Improve_Sizes extends WP_UnitTestCase {

	/**
	 * Set up the environment for the tests.
	 */
	public function setUp(): void {
		parent::setUp();

		switch_theme( 'twentytwentyfour' );
	}

	/**
	 * Test the function with full alignment.
	 *
	 * @covers ::auto_sizes_improve_image_sizes_attribute
	 */
	public function test_full_alignment(): void {
		$content      = '<img src="test.jpg" />';
		$parsed_block = array(
			'attrs' => array(
				'align' => 'full',
			),
		);

		$expected = '<img sizes="100vw" src="test.jpg" />';
		$actual   = auto_sizes_improve_image_sizes_attribute( $content, $parsed_block );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test the function with wide alignment.
	 *
	 * @covers ::auto_sizes_improve_image_sizes_attribute
	 */
	public function test_wide_alignment(): void {
		$content      = '<img src="test.jpg" />';
		$parsed_block = array(
			'attrs' => array(
				'align' => 'wide',
			),
		);

		$expected = '<img sizes="(max-width: 1280px) 100vw, 1280px" src="test.jpg" />';
		$actual   = auto_sizes_improve_image_sizes_attribute( $content, $parsed_block );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test the function with default alignment (contentSize).
	 *
	 * @covers ::auto_sizes_improve_image_sizes_attribute
	 */
	public function test_default_alignment(): void {
		$content      = '<img src="test.jpg" />';
		$parsed_block = array(
			'attrs' => array(
				// No alignment specified.
			),
		);

		$expected = '<img sizes="(max-width: 620px) 100vw, 620px" src="test.jpg" />';
		$actual   = auto_sizes_improve_image_sizes_attribute( $content, $parsed_block );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test the function when no image is present.
	 *
	 * @covers ::auto_sizes_improve_image_sizes_attribute
	 */
	public function test_no_image(): void {
		$content      = '<p>No image here</p>';
		$parsed_block = array();

		$expected = $content;
		$actual   = auto_sizes_improve_image_sizes_attribute( $content, $parsed_block );

		$this->assertEquals( $expected, $actual );
	}
}
