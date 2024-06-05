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
	 * Attachment url.
	 *
	 * @var string
	 */
	public static $image_url;

	/**
	 * Set up the environment for the tests.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		switch_theme( 'twentytwentyfour' );

		self::$image_id  = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
		self::$image_url = wp_get_attachment_image_url( self::$image_id, 'full' );
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
	 * Test the image block with different image sizes and full alignment.
	 *
	 * @dataProvider data_image_sizes
	 *
	 * @param string $image_size Image size.
	 */
	public function test_image_block_with_full_alignment( string $image_size ): void {
		$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"sizeSlug":"' . $image_size . '","linkDestination":"none","align":"full"} --><figure class="wp-block-image size-large"><img src="' . self::$image_url . '" /></figure><!-- /wp:image -->';
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		$this->assertStringContainsString( 'sizes="100vw" ', $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array<array<string>> The image sizes.
	 */
	public function data_image_sizes(): array {
		return array(
			array( 'thumbnail' ),
			array( 'medium' ),
			array( 'large' ),
			array( 'full' ),
		);
	}

	/**
	 * Test the cover block with full alignment.
	 */
	public function test_cover_block_with_full_alignment(): void {
		$block_content = '<!-- wp:cover {"url":"' . self::$image_url . '","id":' . self::$image_id . ',"dimRatio":50,"align":"full","style":{"color":{}}} -->
		<div class="wp-block-cover alignfull"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background wp-image-' . self::$image_id . '" alt="" src="' . self::$image_url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","fontSize":"large"} -->
		<p class="has-text-align-center has-large-font-size"></p>
		<!-- /wp:paragraph --></div></div>
		<!-- /wp:cover -->';
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();
		$this->assertStringContainsString( 'sizes="100vw" ', $result );
	}

	/**
	 * Test the image block with different image sizes and wide alignment.
	 *
	 * @dataProvider data_image_sizes_for_wide_alignment
	 *
	 * @param string $image_size Image size.
	 * @param string $expected   Expected output.
	 */
	public function test_image_block_with_wide_alignment( string $image_size, string $expected ): void {
		$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"sizeSlug":"' . $image_size . '","linkDestination":"none","align":"wide"} --><figure class="wp-block-image size-large"><img src="' . TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg" /></figure><!-- /wp:image -->';
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		$this->assertStringContainsString( $expected, $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array<array<string>> The image sizes.
	 */
	public function data_image_sizes_for_wide_alignment(): array {
		return array(
			'Return thumb size 150px instead of wideSize 1280px'  => array(
				'thumbnail',
				'sizes="(max-width: 150px) 100vw, 150px" ',
			),
			'Return medium size 300px instead of wideSize 1280px' => array(
				'medium',
				'sizes="(max-width: 300px) 100vw, 300px" ',
			),
			'Return large size 1024px instead of wideSize 1280px' => array(
				'large',
				'sizes="(max-width: 1024px) 100vw, 1024px" ',
			),
			'Return full size 1080px instead of wideSize 1280px' => array(
				'full',
				'sizes="(max-width: 1080px) 100vw, 1080px" ',
			),
		);
	}

	/**
	 * Test the cover block with wide alignment.
	 */
	public function test_cover_block_with_wide_alignment(): void {
		// Cover block don't have image size setting so it will load full size.
		$block_content = '<!-- wp:cover {"url":"' . self::$image_url . '","id":' . self::$image_id . ',"dimRatio":50,"align":"wide","style":{"color":{}}} -->
		<div class="wp-block-cover alignwide"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background wp-image-' . self::$image_id . '" alt="" src="' . self::$image_url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","fontSize":"large"} -->
		<p class="has-text-align-center has-large-font-size"></p>
		<!-- /wp:paragraph --></div></div>
		<!-- /wp:cover -->';
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();
		$this->assertStringContainsString( 'sizes="(max-width: 1080px) 100vw, 1080px" ', $result );
	}

	/**
	 * Test the function with default alignment (contentSize).
	 */
	/**
	 * Test the image block with different image sizes and default alignment (contentSize).
	 *
	 * @dataProvider data_image_sizes_for_default_alignment
	 *
	 * @param string $image_size Image size.
	 * @param string $expected   Expected output.
	 */
	public function test_image_block_with_default_alignment( string $image_size, string $expected ): void {
		$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"sizeSlug":"' . $image_size . '","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="' . TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg" /></figure><!-- /wp:image -->';
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		$this->assertStringContainsString( $expected, $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array<array<string>> The image sizes.
	 */
	public function data_image_sizes_for_default_alignment(): array {
		return array(
			'Return thumb size 150px instead of contentSize 620px'  => array(
				'thumbnail',
				'sizes="(max-width: 150px) 100vw, 150px" ',
			),
			'Return medium size 300px instead of contentSize 620px' => array(
				'medium',
				'sizes="(max-width: 300px) 100vw, 300px" ',
			),
			'Return contentSize 620px instead of large size 1024px' => array(
				'large',
				'sizes="(max-width: 620px) 100vw, 620px" ',
			),
			'Return contentSize 620px instead of full size 1080px' => array(
				'full',
				'sizes="(max-width: 620px) 100vw, 620px" ',
			),
		);
	}

	/**
	 * Test the cover block with default alignment (contentSize).
	 */
	public function test_cover_block_with_default_alignment(): void {
		$block_content = '<!-- wp:cover {"url":"' . self::$image_url . '","id":' . self::$image_id . ',"dimRatio":50,"align":"wide","style":{"color":{}}} -->
		<div class="wp-block-cover alignwide"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background wp-image-' . self::$image_id . '" alt="" src="' . self::$image_url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","fontSize":"large"} -->
		<p class="has-text-align-center has-large-font-size"></p>
		<!-- /wp:paragraph --></div></div>
		<!-- /wp:cover -->';
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();
		$this->assertStringContainsString( 'sizes="(max-width: 1080px) 100vw, 1080px" ', $result );
	}

	/**
	 * Test the function when no image is present.
	 *
	 * @covers ::auto_sizes_improve_image_sizes_attribute
	 */
	public function test_no_image(): void {
		$block_content = '<!-- wp:paragraph -->
		<p>No image here</p>
		<!-- /wp:paragraph -->';
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();

		$this->assertStringContainsString( '<p>No image here</p>', $result );
	}
}
