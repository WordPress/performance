<?php
/**
 * Tests for webp-uploads plugin picture-element.php.
 *
 * @group picture-element
 *
 * @package webp-uploads
 */

use WebP_Uploads\Tests\TestCase;

class Test_WebP_Uploads_Picture_Element extends TestCase {

	/**
	 * Attachment ID.
	 *
	 * @var int
	 */
	public static $image_id;

	/**
	 * Setup shared fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$image_id = $factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
	}

	/**
	 * Test that images are wrapped in picture element when enabled.
	 *
	 * @dataProvider data_provider_it_should_maybe_wrap_images_in_picture_element
	 *
	 * @param bool   $jpeg_and_webp          Whether to enable JPEG and WebP output.
	 * @param bool   $picture_element        Whether to enable picture element output.
	 * @param bool   $expect_picture_element Whether to expect the image to be wrapped in a picture element.
	 * @param string $expected_html          The expected HTML output.
	 */
	public function test_maybe_wrap_images_in_picture_element( bool $jpeg_and_webp, bool $picture_element, bool $expect_picture_element, string $expected_html ): void {
		$mime_type = 'image/webp';
		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime_type ) ) ) {
			$this->markTestSkipped( "Mime type $mime_type is not supported." );
		}

		if ( $jpeg_and_webp ) {
			$this->opt_in_to_jpeg_and_webp();
		}

		if ( $picture_element ) {
			$this->opt_in_to_picture_element();
		}

		// Create an image.
		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );

		// Create some content with the image.
		$the_image = wp_get_attachment_image(
			$attachment_id,
			'medium',
			false,
			array(
				'class' => "wp-image-{$attachment_id}",
				'alt'   => 'Green Leaves',
			)
		);

		$processor = new WP_HTML_Tag_Processor( $the_image );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'IMG' ) ) );
		$width  = (int) $processor->get_attribute( 'width' );
		$height = (int) $processor->get_attribute( 'height' );
		$alt    = (string) $processor->get_attribute( 'alt' );

		$size_to_use                  = ( $width > 0 && $height > 0 ) ? array( $width, $height ) : 'full';
		$image_src                    = wp_get_attachment_image_src( $attachment_id, $size_to_use );
		list( $src, $width, $height ) = $image_src;
		$size_array                   = array( absint( $width ), absint( $height ) );
		$image_meta                   = wp_get_attachment_metadata( $attachment_id );
		$sizes                        = wp_calculate_image_sizes( $size_array, $src, $image_meta, $attachment_id );
		$image_srcset                 = wp_get_attachment_image_srcset( $attachment_id, $size_to_use );

		$img_src = '';
		if ( is_array( $image_src ) ) {
			$img_src = $image_src[0];
		}
		// Remove the last size in the srcset, as it is not needed.
		$jpeg_srcset = substr( $image_srcset, 0, strrpos( $image_srcset, ',' ) );
		$webp_srcset = str_replace( '.jpg', '-jpg.webp', $jpeg_srcset );

		// Prepare the expected HTML by replacing placeholders with expected values.
		$replacements = array(
			'{{img-width}}'         => $width,
			'{{img-height}}'        => $height,
			'{{img-src}}'           => $img_src,
			'{{img-attachment-id}}' => $attachment_id,
			'{{img-alt}}'           => $alt,
			'{{img-srcset}}'        => $image_srcset,
			'{{img-sizes}}'         => $sizes,
			'{{jpeg-srcset}}'       => $jpeg_srcset,
			'{{webp-srcset}}'       => $webp_srcset,
		);

		$expected_html = str_replace( array_keys( $replacements ), array_values( $replacements ), $expected_html );

		// Apply the wp_content_img_tag filter.
		$the_image = apply_filters( 'wp_content_img_tag', $the_image, 'the_content', $attachment_id );

		// Check that the image has the expected HTML.
		$this->assertEquals( $expected_html, $the_image );
	}

	/**
	 * Data provider for it_should_maybe_wrap_images_in_picture_element.
	 *
	 * @return array<string, array<string, bool|string>>
	 */
	public function data_provider_it_should_maybe_wrap_images_in_picture_element(): array {
		return array(
			'jpeg and picture enabled' => array(
				'jpeg_and_webp'          => true,
				'picture_element'        => true,
				'expect_picture_element' => true,
				'expected_html'          => '<picture class="wp-picture-{{img-attachment-id}}" style="display: contents;"><source type="image/webp" srcset="{{webp-srcset}}" sizes="{{img-sizes}}"><source type="image/jpeg" srcset="{{jpeg-srcset}}" sizes="{{img-sizes}}"><img width="{{img-width}}" height="{{img-height}}" src="{{img-src}}" class="wp-image-{{img-attachment-id}}" alt="{{img-alt}}" decoding="async" loading="lazy" srcset="{{img-srcset}}" sizes="{{img-sizes}}" /></picture>',
			),
			'only picture enabled'     => array(
				'jpeg_and_webp'          => false,
				'picture_element'        => true,
				'expect_picture_element' => true,
				'expected_html'          => '<picture class="wp-picture-{{img-attachment-id}}" style="display: contents;"><source type="image/webp" srcset="{{webp-srcset}}" sizes="{{img-sizes}}"><img width="{{img-width}}" height="{{img-height}}" src="{{img-src}}" class="wp-image-{{img-attachment-id}}" alt="{{img-alt}}" decoding="async" loading="lazy" srcset="{{img-srcset}}" sizes="{{img-sizes}}" /></picture>',
			),
			'only jpeg enabled'        => array(
				'jpeg_and_webp'          => true,
				'picture_element'        => false,
				'expect_picture_element' => false,
				'expected_html'          => '<img width="{{img-width}}" height="{{img-height}}" src="{{img-src}}" class="wp-image-{{img-attachment-id}}" alt="{{img-alt}}" decoding="async" loading="lazy" srcset="{{img-srcset}}" sizes="{{img-sizes}}" />',
			),
			'neither enabled'          => array(
				'jpeg_and_webp'          => false,
				'picture_element'        => false,
				'expect_picture_element' => false,
				'expected_html'          => '<img width="{{img-width}}" height="{{img-height}}" src="{{img-src}}" class="wp-image-{{img-attachment-id}}" alt="{{img-alt}}" decoding="async" loading="lazy" srcset="{{img-srcset}}" sizes="{{img-sizes}}" />',
			),
		);
	}

	/**
	 * @dataProvider data_provider_test_image_sizes_equality
	 *
	 * @param Closure|null $add_filter The filter.
	 */
	public function test_img_sizes_is_equal_to_picture_source_sizes_for_picture_element( ?Closure $add_filter ): void {
		$mime_type = 'image/webp';
		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime_type ) ) ) {
			$this->markTestSkipped( "Mime type $mime_type is not supported." );
		}

		$image = wp_get_attachment_image(
			self::$image_id,
			'large',
			false,
			array(
				'class' => 'wp-image-' . self::$image_id,
				'alt'   => 'Green Leaves',
			)
		);

		if ( $add_filter instanceof Closure ) {
			$add_filter();
		}

		$img_markup = apply_filters( 'the_content', $image );

		// Apply picture element support.
		$this->opt_in_to_picture_element();

		$picture_markup = apply_filters( 'the_content', $image );

		$img_processor = new WP_HTML_Tag_Processor( $img_markup );
		$this->assertTrue( $img_processor->next_tag( array( 'tag_name' => 'IMG' ) ), 'There should be an IMG tag.' );
		$img_sizes = $img_processor->get_attribute( 'sizes' );
		$img_src   = $img_processor->get_attribute( 'src' );

		$picture_processor = new WP_HTML_Tag_Processor( $picture_markup );

		$picture_processor->next_tag( array( 'tag_name' => 'IMG' ) );
		$this->assertSame( $img_src, $picture_processor->get_attribute( 'src' ), 'Make sure IMG src is same.' );
		$this->assertSame( $img_sizes, $picture_processor->get_attribute( 'sizes' ), 'The IMG and Picture IMG have same sizes attributes.' );

		while ( $picture_processor->next_tag( array( 'tag_name' => 'source' ) ) ) {
			$this->assertSame( $img_sizes, $picture_processor->get_attribute( 'sizes' ), 'The IMG and Picture source have same sizes attributes.' );
		}
	}

	/**
	 * Data provider for it_should_maybe_wrap_images_in_picture_element.
	 *
	 * @return array<string, array{ add_filter: Closure|null }>
	 */
	public function data_provider_test_image_sizes_equality(): array {
		return array(
			'no_filter'   => array(
				'add_filter' => null,
			),
			'with_filter' => array(
				'add_filter' => function (): void {
					add_filter(
						'wp_content_img_tag',
						function ( string $content ): string {
							$processor = new WP_HTML_Tag_Processor( $content );
							$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'img' ) ) );
							$processor->set_attribute( 'sizes', '(max-width: 333px) 100vw, 333px' );
							return $processor->get_updated_html();
						}
					);
				},
			),
		);
	}

	public function test_disable_responsive_image_with_picture_element(): void {
		$mime_type = 'image/webp';
		if ( ! wp_image_editor_supports( array( 'mime_type' => $mime_type ) ) ) {
			$this->markTestSkipped( "Mime type $mime_type is not supported." );
		}

		// Disable responsive images.
		add_filter( 'wp_calculate_image_sizes', '__return_false' );

		$image = wp_get_attachment_image(
			self::$image_id,
			'large',
			false,
			array(
				'class' => 'wp-image-' . self::$image_id,
				'alt'   => 'Green Leaves',
			)
		);

		$img_markup = apply_filters( 'the_content', $image );

		// Apply picture element support.
		$this->opt_in_to_picture_element();

		$picture_markup = apply_filters( 'the_content', $image );

		$img_processor = new WP_HTML_Tag_Processor( $img_markup );
		$img_processor->next_tag( array( 'tag_name' => 'IMG' ) );

		$this->assertNull( $img_processor->get_attribute( 'sizes' ), 'Sizes attribute missing in IMG tag.' );

		$picture_processor = new WP_HTML_Tag_Processor( $picture_markup );
		$picture_processor->next_tag( array( 'tag_name' => 'IMG' ) );

		$this->assertSame( $img_processor->get_attribute( 'src' ), $picture_processor->get_attribute( 'src' ), 'Make sure IMG src is same.' );
		$this->assertNull( $picture_processor->get_attribute( 'sizes' ), 'Sizes attribute missing in Picture IMG tag.' );

		$picture_processor->next_tag( array( 'tag_name' => 'source' ) );

		$this->assertNull( $picture_processor->get_attribute( 'sizes' ), 'Sizes attribute missing in Picture source tag.' );
	}
}
