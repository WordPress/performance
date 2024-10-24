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

	public function set_up(): void {
		parent::set_up();

		// Disable auto sizes.
		remove_filter( 'wp_content_img_tag', 'auto_sizes_update_content_img_tag' );

		// Disable lazy loading attribute.
		add_filter( 'wp_img_tag_add_loading_attr', '__return_false' );
	}

	/**
	 * Test that if disable responsive image then it will not add sizes attribute.
	 */
	public function test_that_if_disable_responsive_image_then_it_will_not_add_sizes_attribute(): void {
		// Disable responsive images.
		add_filter( 'wp_calculate_image_sizes', '__return_false' );

		$image_size = 'large';

		$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"sizeSlug":"' . $image_size . '","linkDestination":"none"} --><figure class="wp-block-image size-' . $image_size . '"><img src="' . wp_get_attachment_image_url( self::$image_id, $image_size ) . '" alt="" class="wp-image-' . self::$image_id . '"/></figure><!-- /wp:image -->';

		$result = apply_filters( 'the_content', $block_content );

		$img_processor = new WP_HTML_Tag_Processor( $result );
		$this->assertTrue( $img_processor->next_tag( array( 'tag_name' => 'IMG' ) ) );
		$this->assertNull( $img_processor->get_attribute( 'sizes' ), 'The sizes attribute should not added in IMG tag.' );
	}

	/**
	 * Test the image block with different image sizes and full alignment.
	 *
	 * @dataProvider data_image_sizes
	 *
	 * @param string $image_size Image size.
	 */
	public function test_image_block_with_full_alignment( string $image_size ): void {
		$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"sizeSlug":"' . $image_size . '","linkDestination":"none","align":"full"} --><figure class="wp-block-image size-' . $image_size . '"><img src="' . wp_get_attachment_image_url( self::$image_id, $image_size ) . '" alt="" class="wp-image-' . self::$image_id . '"/></figure><!-- /wp:image -->';

		$result = apply_filters( 'the_content', $block_content );

		$this->assertStringContainsString( 'sizes="100vw" ', $result );
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

		$result = apply_filters( 'the_content', $block_content );

		$this->assertStringContainsString( 'sizes="100vw" ', $result );
	}

	/**
	 * Test the image block with different image sizes and wide alignment.
	 *
	 * @dataProvider data_image_sizes
	 *
	 * @param string $image_size Image size.
	 */
	public function test_image_block_with_wide_alignment( string $image_size ): void {
		$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"sizeSlug":"' . $image_size . '","linkDestination":"none","align":"wide"} --><figure class="wp-block-image size-' . $image_size . '"><img src="' . wp_get_attachment_image_url( self::$image_id, $image_size ) . '" alt="" class="wp-image-' . self::$image_id . '"/></figure><!-- /wp:image -->';

		$result = apply_filters( 'the_content', $block_content );

		$this->assertStringContainsString( 'sizes="(max-width: 1280px) 100vw, 1280px" ', $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array<array<string>> The image sizes.
	 */
	public function data_image_sizes(): array {
		return array(
			'Return full or wideSize 1280px instead of medium size 300px'  => array(
				'medium',
			),
			'Return full or wideSize 1280px instead of large size 1024px'  => array(
				'large',
			),
			'Return full or wideSize 1280px instead of full size 1080px'  => array(
				'full',
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

		$result = apply_filters( 'the_content', $block_content );

		$this->assertStringContainsString( 'sizes="(max-width: 1280px) 100vw, 1280px" ', $result );
	}

	/**
	 * Test the image block with different image sizes and default alignment (contentSize).
	 *
	 * @dataProvider data_image_sizes_for_default_alignment
	 *
	 * @param string $image_size Image size.
	 * @param string $expected   Expected output.
	 * @param bool   $is_resize  Whether resize or not.
	 */
	public function test_image_block_with_default_alignment( string $image_size, string $expected, bool $is_resize = false ): void {
		if ( $is_resize ) {
			$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"width":"100px","sizeSlug":"' . $image_size . '","linkDestination":"none"} --><figure class="wp-block-image size-' . $image_size . '"><img src="' . wp_get_attachment_image_url( self::$image_id, $image_size ) . '"  style="width:100px" alt="" class="wp-image-' . self::$image_id . '"/></figure><!-- /wp:image -->';
		} else {
			$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"sizeSlug":"' . $image_size . '","linkDestination":"none"} --><figure class="wp-block-image size-' . $image_size . '"><img src="' . wp_get_attachment_image_url( self::$image_id, $image_size ) . '" alt="" class="wp-image-' . self::$image_id . '"/></figure><!-- /wp:image -->';
		}

		$result = apply_filters( 'the_content', $block_content );

		$this->assertStringContainsString( $expected, $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array<int, bool|string>> The image sizes.
	 */
	public function data_image_sizes_for_default_alignment(): array {
		return array(
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
			'Return resized size 100px instead of contentSize 620px or medium image size 300px'    => array(
				'medium',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				true,
			),
			'Return resized size 100px instead of contentSize 620px or large image size 1024px'    => array(
				'large',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				true,
			),
			'Return resized size 100px instead of contentSize 620px or full image size 1080px'     => array(
				'full',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				true,
			),
		);
	}

	/**
	 * Test the cover block with default alignment (contentSize).
	 */
	public function test_cover_block_with_default_alignment(): void {
		$image_url     = wp_get_attachment_image_url( self::$image_id, 'full' );
		$block_content = '<!-- wp:cover {"url":"' . $image_url . '","id":' . self::$image_id . ',"dimRatio":50,"style":{"color":{}}} -->
		<div class="wp-block-cover alignwide"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background wp-image-' . self::$image_id . '" alt="" src="' . $image_url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","fontSize":"large"} -->
		<p class="has-text-align-center has-large-font-size"></p>
		<!-- /wp:paragraph --></div></div>
		<!-- /wp:cover -->';

		$result = apply_filters( 'the_content', $block_content );

		$this->assertStringContainsString( 'sizes="(max-width: 620px) 100vw, 620px" ', $result );
	}

	/**
	 * Test the image block with different image sizes and left, right and center alignment.
	 *
	 * @dataProvider data_image_sizes_for_left_right_center_alignment
	 *
	 * @param string $image_size Image size.
	 * @param string $expected   Expected output.
	 * @param string $alignment  Alignment of the image.
	 * @param bool   $is_resize  Whether resize or not.
	 */
	public function test_image_block_with_left_right_center_alignment( string $image_size, string $expected, string $alignment, bool $is_resize = false ): void {
		if ( $is_resize ) {
			$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"width":"100px","sizeSlug":"' . $image_size . '","linkDestination":"none","align":"' . $alignment . '"} --><figure class="wp-block-image size-' . $image_size . '"><img src="' . wp_get_attachment_image_url( self::$image_id, $image_size ) . '"  style="width:100px" alt="" class="wp-image-' . self::$image_id . '"/></figure><!-- /wp:image -->';
		} else {
			$block_content = '<!-- wp:image {"id":' . self::$image_id . ',"sizeSlug":"' . $image_size . '","linkDestination":"none","align":"' . $alignment . '"} --><figure class="wp-block-image size-' . $image_size . '"><img src="' . wp_get_attachment_image_url( self::$image_id, $image_size ) . '" alt="" class="wp-image-' . self::$image_id . '"/></figure><!-- /wp:image -->';
		}

		$result = apply_filters( 'the_content', $block_content );

		$this->assertStringContainsString( $expected, $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array<int, bool|string>> The image sizes and alignments.
	 */
	public function data_image_sizes_for_left_right_center_alignment(): array {
		return array(
			'Return medium image size 300px with left alignment'                                    => array(
				'medium',
				'sizes="(max-width: 300px) 100vw, 300px" ',
				'left',
			),
			'Return large image size 1024px with left alignment'                                    => array(
				'large',
				'sizes="(max-width: 1024px) 100vw, 1024px" ',
				'left',
			),
			'Return full image size 1080px with left alignment'                                     => array(
				'full',
				'sizes="(max-width: 1080px) 100vw, 1080px" ',
				'left',
			),
			'Return medium image size 300px with right alignment'                                   => array(
				'medium',
				'sizes="(max-width: 300px) 100vw, 300px" ',
				'right',
			),
			'Return large image size 1024px with right alignment'                                   => array(
				'large',
				'sizes="(max-width: 1024px) 100vw, 1024px" ',
				'right',
			),
			'Return full image size 1080px with right alignment'                                    => array(
				'full',
				'sizes="(max-width: 1080px) 100vw, 1080px" ',
				'right',
			),
			'Return medium image size 300px with center alignment'                                  => array(
				'medium',
				'sizes="(max-width: 300px) 100vw, 300px" ',
				'center',
			),
			'Return large image size 1024px with center alignment'                                  => array(
				'large',
				'sizes="(max-width: 1024px) 100vw, 1024px" ',
				'center',
			),
			'Return full image size 1080px with center alignment'                                   => array(
				'full',
				'sizes="(max-width: 1080px) 100vw, 1080px" ',
				'center',
			),
			'Return resized size 100px instead of medium image size 300px with left alignment'      => array(
				'medium',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				'left',
				true,
			),
			'Return resized size 100px instead of large image size 1024px with left alignment'      => array(
				'large',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				'left',
				true,
			),
			'Return resized size 100px instead of full image size 1080px with left alignment'       => array(
				'full',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				'left',
				true,
			),
			'Return resized size 100px instead of medium image size 300px with right alignment'     => array(
				'medium',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				'right',
				true,
			),
			'Return resized size 100px instead of large image size 1024px with right alignment'     => array(
				'large',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				'right',
				true,
			),
			'Return resized size 100px instead of full image size 1080px with right alignment'      => array(
				'full',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				'right',
				true,
			),
			'Return resized size 100px instead of medium image size 300px with center alignment'    => array(
				'medium',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				'center',
				true,
			),
			'Return resized size 100px instead of large image size 1024px with center alignment'    => array(
				'large',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				'center',
				true,
			),
			'Return resized size 100px instead of full image size 1080px with center alignment'     => array(
				'full',
				'sizes="(max-width: 100px) 100vw, 100px" ',
				'center',
				true,
			),
		);
	}

	/**
	 * Test the cover block with left and right alignment.
	 *
	 * @dataProvider data_image_left_right_center_alignment
	 *
	 * @param string $alignment Alignment of the image.
	 */
	public function test_cover_block_with_left_right_center_alignment( string $alignment ): void {
		$image_url     = wp_get_attachment_image_url( self::$image_id, 'full' );
		$block_content = '<!-- wp:cover {"url":"' . $image_url . '","id":' . self::$image_id . ',"dimRatio":50,"align":"' . $alignment . '","style":{"color":{}}} -->
		<div class="wp-block-cover align' . $alignment . '"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background wp-image-' . self::$image_id . '" alt="" src="' . $image_url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","fontSize":"large"} -->
		<p class="has-text-align-center has-large-font-size"></p>
		<!-- /wp:paragraph --></div></div>
		<!-- /wp:cover -->';

		$result = apply_filters( 'the_content', $block_content );

		$this->assertStringContainsString( 'sizes="(max-width: 1080px) 100vw, 1080px" ', $result );
	}

	/**
	 * Data provider.
	 *
	 * @return array<array<string>> The image sizes.
	 */
	public function data_image_left_right_center_alignment(): array {
		return array(
			array( 'left' ),
			array( 'right' ),
			array( 'center' ),
		);
	}

	/**
	 * Test the function when no image is present.
	 */
	public function test_no_image(): void {
		$block_content = '<!-- wp:paragraph -->
		<p>No image here</p>
		<!-- /wp:paragraph -->';

		$result = apply_filters( 'the_content', $block_content );

		$this->assertStringContainsString( '<p>No image here</p>', $result );
	}
}
