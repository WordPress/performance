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
	 * Test the image block with different image sizes and full alignment.
	 *
	 * @dataProvider data_image_sizes
	 *
	 * @param string $image_size Image size.
	 */
	public function test_image_block_with_full_alignment( string $image_size ): void {
		$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"sizeSlug":"' . $image_size . '","linkDestination":"none","align":"full"} --><figure class="wp-block-image size-' . $image_size . '"><img src="' . wp_get_attachment_image_url( self::$image_id, $image_size ) . '" /></figure><!-- /wp:image -->';
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
		$image_url     = wp_get_attachment_image_url( self::$image_id, 'full' );
		$block_content = '<!-- wp:cover {"url":"' . $image_url . '","id":' . self::$image_id . ',"dimRatio":50,"align":"full","style":{"color":{}}} -->
		<div class="wp-block-cover alignfull"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background wp-image-' . self::$image_id . '" alt="" src="' . $image_url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","fontSize":"large"} -->
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
		$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"sizeSlug":"' . $image_size . '","linkDestination":"none","align":"wide"} --><figure class="wp-block-image size-' . $image_size . '"><img src="' . wp_get_attachment_image_url( self::$image_id, $image_size ) . '" /></figure><!-- /wp:image -->';
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
		$image_url     = wp_get_attachment_image_url( self::$image_id, 'full' );
		$block_content = '<!-- wp:cover {"url":"' . $image_url . '","id":' . self::$image_id . ',"dimRatio":50,"align":"wide","style":{"color":{}}} -->
		<div class="wp-block-cover alignwide"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background wp-image-' . self::$image_id . '" alt="" src="' . $image_url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","fontSize":"large"} -->
		<p class="has-text-align-center has-large-font-size"></p>
		<!-- /wp:paragraph --></div></div>
		<!-- /wp:cover -->';
		$parsed_blocks = parse_blocks( $block_content );
		$block         = new WP_Block( $parsed_blocks[0] );
		$result        = $block->render();
		$this->assertStringContainsString( 'sizes="(max-width: 1080px) 100vw, 1080px" ', $result );
	}

	/**
	 * Test the image block with different image sizes and default alignment (contentSize).
	 *
	 * @dataProvider data_image_sizes_for_default_alignment
	 *
	 * @param string $image_size Image size.
	 * @param string $expected   Expected output.
	 * @param string $is_resize  Whether resize or not.
	 */
	public function test_image_block_with_default_alignment( string $image_size, string $expected, string $is_resize = '' ): void {
		if ( $is_resize ) {
			$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"width":"100px","sizeSlug":"' . $image_size . '","linkDestination":"none"} --><figure class="wp-block-image size-' . $image_size . '"><img src="' . wp_get_attachment_image_url( self::$image_id, $image_size ) . '"  style="width:100px" /></figure><!-- /wp:image -->';
		} else {
			$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"sizeSlug":"' . $image_size . '","linkDestination":"none"} --><figure class="wp-block-image size-' . $image_size . '"><img src="' . wp_get_attachment_image_url( self::$image_id, $image_size ) . '" /></figure><!-- /wp:image -->';
		}
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
			'Return thumbnail image size 150px instead of contentSize 620px'                       => array(
				'thumbnail',
				'sizes="(max-width: 150px) 100vw, 150px" ',
			),
			'Return medium image size 300px instead of contentSize 620px'                          => array(
				'medium',
				'sizes="(max-width: 300px) 100vw, 300px" ',
			),
			'Return contentSize 620px instead of large image size 1024px'                          => array(
				'large',
				'sizes="(max-width: 620px) 100vw, 620px" ',
			),
			'Return contentSize 620px instead of full image size 1080px'                           => array(
				'full',
				'sizes="(max-width: 620px) 100vw, 620px" ',
			),
			'Return resized size 100px instead of contentSize 620px or thumbnail image size 150px' => array(
				'thumbnail',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				'yes',
			),
			'Return resized size 100px instead of contentSize 620px or medium image size 300px'    => array(
				'medium',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				'yes',
			),
			'Return resized size 100px instead of contentSize 620px or large image size 1024px'    => array(
				'large',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				'yes',
			),
			'Return resized size 100px instead of contentSize 620px or full image size 1080px'     => array(
				'full',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				'yes',
			),
		);
	}

	/**
	 * Test the cover block with default alignment (contentSize).
	 */
	public function test_cover_block_with_default_alignment(): void {
		$image_url     = wp_get_attachment_image_url( self::$image_id, 'full' );
		$block_content = '<!-- wp:cover {"url":"' . $image_url . '","id":' . self::$image_id . ',"dimRatio":50,"align":"wide","style":{"color":{}}} -->
		<div class="wp-block-cover alignwide"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background wp-image-' . self::$image_id . '" alt="" src="' . $image_url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","fontSize":"large"} -->
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
