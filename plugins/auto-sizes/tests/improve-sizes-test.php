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
	 */
	public function test_full_alignment(): void {
		$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"sizeSlug":"large","linkDestination":"none","align":"full"} --><figure class="wp-block-image size-large"><img src="' . TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg" /></figure><!-- /wp:image -->';
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		$this->assertStringContainsString( 'sizes="100vw" ', $result );
	}

	/**
	 * Test the function with wide alignment.
	 */
	public function test_wide_alignment(): void {
		$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"sizeSlug":"large","linkDestination":"none","align":"wide"} --><figure class="wp-block-image size-large"><img src="' . TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg" /></figure><!-- /wp:image -->';
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		$this->assertStringContainsString( 'sizes="(max-width: 1024px) 100vw, 1024px" ', $result );
	}

	/**
	 * Test the function with default alignment (contentSize).
	 */
	public function test_default_alignment(): void {
		$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="' . TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg" /></figure><!-- /wp:image -->';
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

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
