<?php
/**
 * Tests for the improve sizes for Images.
 *
 * @package auto-sizes
 * @group   improve-sizes
 */

class Tests_Improve_Sizes extends WP_UnitTestCase {

	/**
	 * Attachment ID.
	 *
	 * @var int
	 */
	public static $image_id;

	/**
	 * Set up the environment for the tests.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		switch_theme( 'twentytwentyfour' );

		self::$image_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
	}

	/**
	 * Helper function to create image markup from a given attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Image markup.
	 */
	public function get_image_tag( int $attachment_id ): string {
		return get_image_tag( $attachment_id, '', '', '', 'large' );
	}

	/**
	 * Test the function with full alignment.
	 *
	 * @covers ::auto_sizes_improve_image_sizes_attribute
	 */
	public function test_full_alignment(): void {
		$content      = $this->get_image_tag( self::$image_id );
		$parsed_block = array(
			'attrs' => array(
				'align' => 'full',
			),
		);

		$result = auto_sizes_improve_image_sizes_attribute( $content, $parsed_block );
		$this->assertStringContainsString( 'sizes="100vw" ', $result );
	}

	/**
	 * Test the function with wide alignment.
	 *
	 * @covers ::auto_sizes_improve_image_sizes_attribute
	 */
	public function test_wide_alignment(): void {
		$content      = $this->get_image_tag( self::$image_id );
		$parsed_block = array(
			'attrs' => array(
				'align' => 'wide',
			),
		);

		$expected = '<img sizes="(max-width: 1280px) 100vw, 1280px" src="test.jpg" />';
		$result   = auto_sizes_improve_image_sizes_attribute( $content, $parsed_block );

		$this->assertStringContainsString( 'sizes="(max-width: 1280px) 100vw, 1280px" ', $result );
	}

	/**
	 * Test the function with default alignment (contentSize).
	 *
	 * @covers ::auto_sizes_improve_image_sizes_attribute
	 */
	public function test_default_alignment(): void {
		$content      = $this->get_image_tag( self::$image_id );
		$parsed_block = array(
			'attrs' => array(
				// No alignment specified.
			),
		);

		$expected = '<img sizes="(max-width: 620px) 100vw, 620px" src="test.jpg" />';
		$result   = auto_sizes_improve_image_sizes_attribute( $content, $parsed_block );

		$this->assertStringContainsString( 'sizes="(max-width: 620px) 100vw, 620px" ', $result );
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

		$this->assertSame( $expected, $actual );
	}
}
