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
	 * Mime type.
	 *
	 * @var string
	 */
	public static $mime_type = 'image/webp';

	/**
	 * Attachment ID.
	 *
	 * @var int
	 */
	public static $image_id;

	public function set_up(): void {
		parent::set_up();

		if ( ! wp_image_editor_supports( array( 'mime_type' => self::$mime_type ) ) ) {
			$this->markTestSkipped( 'Mime type image/webp is not supported.' );
		}

		// Default to webp output for tests.
		$this->set_image_output_type( 'webp' );

		// Run critical hooks to satisfy webp_uploads_in_frontend_body() conditions.
		$this->mock_frontend_body_hooks();
	}

	/**
	 * Setup shared fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		// Fallback to JPEG IMG.
		update_option( 'perflab_generate_webp_and_jpeg', '1' );

		self::$image_id = $factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
	}

	/**
	 * Test that images are wrapped in picture element when enabled.
	 *
	 * @dataProvider data_provider_it_should_maybe_wrap_images_in_picture_element
	 *
	 * @param bool   $fallback_jpeg   Whether to fallback JPEG output.
	 * @param bool   $picture_element Whether to enable picture element output.
	 * @param string $expected_html   The expected HTML output.
	 */
	public function test_maybe_wrap_images_in_picture_element( bool $fallback_jpeg, bool $picture_element, string $expected_html ): void {
		update_option( 'perflab_generate_webp_and_jpeg', $fallback_jpeg );

		// Apply picture element support.
		if ( $picture_element ) {
			$this->opt_in_to_picture_element();
		}

		// Create some content with the image.
		$image = wp_get_attachment_image(
			self::$image_id,
			'large',
			false,
			array(
				'class' => 'wp-image-' . self::$image_id,
				'alt'   => 'Green Leaves',
			)
		);

		$processor = new WP_HTML_Tag_Processor( $image );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'IMG' ) ) );
		$width  = (int) $processor->get_attribute( 'width' );
		$height = (int) $processor->get_attribute( 'height' );
		$alt    = (string) $processor->get_attribute( 'alt' );

		$size_to_use                  = ( $width > 0 && $height > 0 ) ? array( $width, $height ) : 'full';
		$image_src                    = wp_get_attachment_image_src( self::$image_id, $size_to_use );
		list( $src, $width, $height ) = $image_src;
		$size_array                   = array( absint( $width ), absint( $height ) );
		$image_meta                   = wp_get_attachment_metadata( self::$image_id );
		$sizes                        = wp_calculate_image_sizes( $size_array, $src, $image_meta, self::$image_id );
		$image_srcset                 = wp_get_attachment_image_srcset( self::$image_id, $size_to_use );

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
			'{{img-attachment-id}}' => self::$image_id,
			'{{img-alt}}'           => $alt,
			'{{img-srcset}}'        => $image_srcset,
			'{{img-sizes}}'         => $sizes,
			'{{webp-srcset}}'       => $webp_srcset,
		);

		$expected_html = str_replace( array_keys( $replacements ), array_values( $replacements ), $expected_html );

		// Apply the wp_content_img_tag filter.
		$image = apply_filters( 'wp_content_img_tag', $image, 'the_content', self::$image_id );

		// Check that the image has the expected HTML.
		$this->assertEquals( $expected_html, $image );
	}

	/**
	 * Data provider for it_should_maybe_wrap_images_in_picture_element.
	 *
	 * @return array<string, array<string, bool|string>>
	 */
	public function data_provider_it_should_maybe_wrap_images_in_picture_element(): array {
		return array(
			'jpeg and picture enabled' => array(
				'fallback_jpeg'   => true,
				'picture_element' => true,
				'expected_html'   => '<picture class="wp-picture-{{img-attachment-id}}" style="display: contents;"><source type="image/webp" srcset="{{webp-srcset}}" sizes="{{img-sizes}}"><img width="{{img-width}}" height="{{img-height}}" src="{{img-src}}" class="wp-image-{{img-attachment-id}}" alt="{{img-alt}}" decoding="async" loading="lazy" srcset="{{img-srcset}}" sizes="{{img-sizes}}" /></picture>',
			),
			'only picture enabled'     => array(
				'fallback_jpeg'   => false,
				'picture_element' => true,
				'expected_html'   => '<img width="{{img-width}}" height="{{img-height}}" src="{{img-src}}" class="wp-image-{{img-attachment-id}}" alt="{{img-alt}}" decoding="async" loading="lazy" srcset="{{img-srcset}}" sizes="{{img-sizes}}" />',
			),
			'only jpeg enabled'        => array(
				'fallback_jpeg'   => true,
				'picture_element' => false,
				'expected_html'   => '<img width="{{img-width}}" height="{{img-height}}" src="{{img-src}}" class="wp-image-{{img-attachment-id}}" alt="{{img-alt}}" decoding="async" loading="lazy" srcset="{{img-srcset}}" sizes="{{img-sizes}}" />',
			),
			'neither enabled'          => array(
				'fallback_jpeg'   => false,
				'picture_element' => false,
				'expected_html'   => '<img width="{{img-width}}" height="{{img-height}}" src="{{img-src}}" class="wp-image-{{img-attachment-id}}" alt="{{img-alt}}" decoding="async" loading="lazy" srcset="{{img-srcset}}" sizes="{{img-sizes}}" />',
			),
		);
	}

	public function test_picture_source_only_have_additional_mime_not_jpeg_and_return_jpeg_fallback(): void {
		// Create some content with the image.
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

		$img_processor = new WP_HTML_Tag_Processor( $img_markup );
		$this->assertTrue( $img_processor->next_tag( array( 'tag_name' => 'IMG' ) ), 'There should be an IMG tag.' );
		$img_src = $img_processor->get_attribute( 'src' );
		$this->assertStringEndsWith( '.webp', $img_src, 'Make sure the IMG should return WEBP src.' );
		$img_srcset = $img_processor->get_attribute( 'srcset' );
		$this->assertStringContainsString( '.webp', $img_srcset, 'Make sure the IMG srcset should return WEBP images.' );

		// Apply picture element support.
		$this->opt_in_to_picture_element();

		$picture_markup    = apply_filters( 'the_content', $image );
		$picture_processor = new WP_HTML_Tag_Processor( $picture_markup );

		$picture_processor->next_tag( array( 'tag_name' => 'IMG' ) );
		// The fallback image should be JPEG.
		$this->assertStringEndsWith( '.jpg', $picture_processor->get_attribute( 'src' ), 'Make sure the fallback IMG should return JPEG src.' );
		$this->assertStringContainsString( '.jpg', $picture_processor->get_attribute( 'srcset' ), 'Make sure the IMG srcset should return JPEG images.' );

		$picture_processor = new WP_HTML_Tag_Processor( $picture_markup );
		while ( $picture_processor->next_tag( array( 'tag_name' => 'source' ) ) ) {
			$this->assertSame( self::$mime_type, $picture_processor->get_attribute( 'type' ), 'Make sure the Picture source should not return JPEG as source.' );
			$this->assertStringContainsString( '.webp', $picture_processor->get_attribute( 'srcset' ), 'Make sure the Picture source srcset should return WEBP images.' );
		}
	}

	/**
	 * @dataProvider data_provider_test_image_sizes_equality
	 *
	 * @param Closure|null $add_filter The filter.
	 */
	public function test_img_sizes_is_equal_to_picture_source_sizes_for_picture_element( ?Closure $add_filter ): void {
		// Create some content with the image.
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

		$img_processor = new WP_HTML_Tag_Processor( $img_markup );
		$this->assertTrue( $img_processor->next_tag( array( 'tag_name' => 'IMG' ) ), 'There should be an IMG tag.' );
		$img_sizes = $img_processor->get_attribute( 'sizes' );

		// Apply picture element support.
		$this->opt_in_to_picture_element();

		$picture_markup    = apply_filters( 'the_content', $image );
		$picture_processor = new WP_HTML_Tag_Processor( $picture_markup );

		$picture_processor->next_tag( array( 'tag_name' => 'IMG' ) );
		$this->assertSame( $img_sizes, $picture_processor->get_attribute( 'sizes' ), 'The IMG and Picture IMG have same sizes attributes.' );

		$picture_processor = new WP_HTML_Tag_Processor( $picture_markup );
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
		// Disable responsive images.
		add_filter( 'wp_calculate_image_sizes', '__return_false' );

		// Create some content with the image.
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

		$img_processor = new WP_HTML_Tag_Processor( $img_markup );
		$this->assertTrue( $img_processor->next_tag( array( 'tag_name' => 'IMG' ) ), 'There should be an IMG tag.' );
		$img_src = $img_processor->get_attribute( 'src' );
		$this->assertStringEndsWith( '.webp', $img_src, 'Make sure the IMG should return WEBP src.' );
		$this->assertNull( $img_processor->get_attribute( 'sizes' ), 'Make sure the sizes attribute is missing in IMG tag.' );
		$this->assertNull( $img_processor->get_attribute( 'srcset' ), 'Make sure the srcset attribute is missing in IMG tag.' );

		// Apply picture element support.
		$this->opt_in_to_picture_element();

		$picture_markup = apply_filters( 'the_content', $image );

		$picture_processor = new WP_HTML_Tag_Processor( $picture_markup );
		$picture_processor->next_tag( array( 'tag_name' => 'IMG' ) );

		// The fallback image should be JPEG.
		$this->assertStringEndsWith( '.jpg', $picture_processor->get_attribute( 'src' ), 'Make sure the fallback IMG should return JPEG src.' );

		$picture_processor = new WP_HTML_Tag_Processor( $picture_markup );
		while ( $picture_processor->next_tag( array( 'tag_name' => 'source' ) ) ) {
			$this->assertSame( self::$mime_type, $picture_processor->get_attribute( 'type' ), 'Make sure the Picture source should not return JPEG as source.' );
			$this->assertStringContainsString( '.webp', $picture_processor->get_attribute( 'srcset' ), 'Make sure the Picture source srcset should return WEBP images.' );
		}
	}
}
