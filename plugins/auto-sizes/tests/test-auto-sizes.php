<?php
/**
 * Tests for the Enhanced Responsive Images plugin.
 *
 * @package auto-sizes
 * @group   auto-sizes
 */

class Test_AutoSizes extends WP_UnitTestCase {

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
	 * Helper function to create image markup from a given attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Image markup.
	 */
	public function get_image_tag( int $attachment_id ): string {
		return get_image_tag( $attachment_id, '', '', '', 'large' );
	}

	public function test_hooks(): void {
		$this->assertSame( 10, has_filter( 'wp_get_attachment_image_attributes', 'auto_sizes_update_image_attributes' ) );
		$this->assertSame( 10, has_filter( 'wp_content_img_tag', 'auto_sizes_update_content_img_tag' ) );
		$this->assertSame( 10, has_action( 'wp_head', 'auto_sizes_render_generator' ) );
	}

	/**
	 * Test generated markup for an image with lazy loading gets auto-sizes.
	 *
	 * @covers ::auto_sizes_update_image_attributes
	 */
	public function test_image_with_lazy_loading_has_auto_sizes(): void {
		$this->assertStringContainsString(
			'sizes="auto, ',
			wp_get_attachment_image( self::$image_id, 'large', false, array( 'loading' => 'lazy' ) )
		);
	}

	/**
	 * Test generated markup for an image without lazy loading does not get auto-sizes.
	 *
	 * @covers ::auto_sizes_update_image_attributes
	 */
	public function test_image_without_lazy_loading_does_not_have_auto_sizes(): void {
		$this->assertStringContainsString(
			'sizes="(max-width: 1024px) 100vw, 1024px"',
			wp_get_attachment_image( self::$image_id, 'large', false, array( 'loading' => '' ) )
		);
	}

	/**
	 * Test content filtered markup with lazy loading gets auto-sizes.
	 *
	 * @covers ::auto_sizes_update_content_img_tag
	 */
	public function test_content_image_with_lazy_loading_has_auto_sizes(): void {
		// Force lazy loading attribute.
		add_filter( 'wp_img_tag_add_loading_attr', '__return_true' );

		$this->assertStringContainsString(
			'sizes="auto, (max-width: 1024px) 100vw, 1024px"',
			wp_filter_content_tags( $this->get_image_tag( self::$image_id ) )
		);
	}

	/**
	 * Test content filtered markup without lazy loading does not get auto-sizes.
	 *
	 * @covers ::auto_sizes_update_content_img_tag
	 */
	public function test_content_image_without_lazy_loading_does_not_have_auto_sizes(): void {
		// Disable lazy loading attribute.
		add_filter( 'wp_img_tag_add_loading_attr', '__return_false' );

		$this->assertStringContainsString(
			'sizes="(max-width: 1024px) 100vw, 1024px"',
			wp_filter_content_tags( $this->get_image_tag( self::$image_id ) )
		);
	}

	/**
	 * Test generated markup for an image with 'auto' keyword already present in sizes does not receive it again.
	 *
	 * @covers ::auto_sizes_update_image_attributes
	 * @covers ::auto_sizes_attribute_includes_valid_auto
	 * @dataProvider data_image_with_existing_auto_sizes
	 */
	public function test_image_with_existing_auto_sizes_is_not_processed_again( string $initial_sizes, bool $expected_processed ): void {
		$image_tag = wp_get_attachment_image(
			self::$image_id,
			'large',
			false,
			array(
				// Force pre-existing 'sizes' attribute and lazy-loading.
				'sizes'   => $initial_sizes,
				'loading' => 'lazy',
			)
		);
		if ( $expected_processed ) {
			$this->assertStringContainsString( 'sizes="auto, ' . $initial_sizes . '"', $image_tag );
		} else {
			$this->assertStringContainsString( 'sizes="' . $initial_sizes . '"', $image_tag );
		}
	}

	/**
	 * Test content filtered markup with 'auto' keyword already present in sizes does not receive it again.
	 *
	 * @covers ::auto_sizes_update_content_img_tag
	 * @covers ::auto_sizes_attribute_includes_valid_auto
	 * @dataProvider data_image_with_existing_auto_sizes
	 */
	public function test_content_image_with_existing_auto_sizes_is_not_processed_again( string $initial_sizes, bool $expected_processed ): void {
		// Force lazy loading attribute.
		add_filter( 'wp_img_tag_add_loading_attr', '__return_true' );

		add_filter(
			'get_image_tag',
			static function ( $html ) use ( $initial_sizes ) {
				return str_replace(
					'" />',
					'" sizes="' . $initial_sizes . '" />',
					$html
				);
			}
		);

		$image_content = wp_filter_content_tags( $this->get_image_tag( self::$image_id ) );
		if ( $expected_processed ) {
			$this->assertStringContainsString( 'sizes="auto, ' . $initial_sizes . '"', $image_content );
		} else {
			$this->assertStringContainsString( 'sizes="' . $initial_sizes . '"', $image_content );
		}
	}

	/**
	 * Returns data for the above test methods to assert correct behavior with a pre-existing sizes attribute.
	 *
	 * @return array<string, mixed[]> Arguments for the test scenarios.
	 */
	public function data_image_with_existing_auto_sizes(): array {
		return array(
			'not present'                 => array(
				'(max-width: 1024px) 100vw, 1024px',
				true,
			),
			'in beginning, without space' => array(
				'auto,(max-width: 1024px) 100vw, 1024px',
				false,
			),
			'in beginning, with space'    => array(
				'auto, (max-width: 1024px) 100vw, 1024px',
				false,
			),
			'sole keyword'                => array(
				'auto',
				false,
			),
			'with space before'           => array(
				' auto, (max-width: 1024px) 100vw, 1024px',
				false,
			),
			'with uppercase'              => array(
				'AUTO, (max-width: 1024px) 100vw, 1024px',
				false,
			),

			/*
			 * The following scenarios technically include the 'auto' keyword,
			 * but it is in the wrong place, as per the HTML spec it must be
			 * the first entry in the list.
			 * Therefore in these invalid cases the 'auto' keyword should still
			 * be added to the beginning of the list.
			 */
			'within, without space'       => array(
				'(max-width: 1024px) 100vw, auto,1024px',
				true,
			),
			'within, with space'          => array(
				'(max-width: 1024px) 100vw, auto, 1024px',
				true,
			),
			'at the end, without space'   => array(
				'(max-width: 1024px) 100vw,auto',
				true,
			),
			'at the end, with space'      => array(
				'(max-width: 1024px) 100vw, auto',
				true,
			),
		);
	}

	/**
	 * Test printing the meta generator tag.
	 *
	 * @covers ::auto_sizes_render_generator
	 */
	public function test_auto_sizes_render_generator(): void {
		$tag = get_echo( 'auto_sizes_render_generator' );
		$this->assertStringStartsWith( '<meta', $tag );
		$this->assertStringContainsString( 'generator', $tag );
		$this->assertStringContainsString( 'auto-sizes ' . IMAGE_AUTO_SIZES_VERSION, $tag );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function data_provider_to_test_auto_sizes_update_content_img_tag(): array {
		return array(
			'expected_with_single_quoted_attributes'       => array(
				'input'    => "<img src='https://example.com/foo-300x225.jpg' srcset='https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w' sizes='(max-width: 650px) 100vw, 650px' loading='lazy'>",
				'expected' => "<img src='https://example.com/foo-300x225.jpg' srcset='https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w' sizes=\"auto, (max-width: 650px) 100vw, 650px\" loading='lazy'>",
			),
			'expected_with_data_sizes_attribute'           => array(
				'input'    => '<img data-tshirt-sizes="S M L" src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 650px) 100vw, 650px" loading="lazy">',
				'expected' => '<img data-tshirt-sizes="S M L" src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="auto, (max-width: 650px) 100vw, 650px" loading="lazy">',
			),
			'expected_with_data_sizes_attribute_already_present' => array(
				'input'    => '<img data-tshirt-sizes="S M L" src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="AUTO, (max-width: 650px) 100vw, 650px" loading="lazy">',
				'expected' => '<img data-tshirt-sizes="S M L" src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="AUTO, (max-width: 650px) 100vw, 650px" loading="lazy">',
			),
			'not_expected_with_loading_lazy_in_attr_value' => array(
				'input'    => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 650px) 100vw, 650px" alt=\'This is the LCP image and it should not get loading="lazy"!\'>',
				'expected' => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 650px) 100vw, 650px" alt=\'This is the LCP image and it should not get loading="lazy"!\'>',
			),
			'not_expected_with_data_loading_attribute_present' => array(
				'input'    => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 650px) 100vw, 650px" data-removed-loading="lazy">',
				'expected' => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 650px) 100vw, 650px" data-removed-loading="lazy">',
			),
			'expected_when_attributes_have_spaces_after_them' => array(
				'input'    => '<img src = "https://example.com/foo-300x225.jpg" srcset = "https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes = "(max-width: 650px) 100vw, 650px" loading = "lazy">',
				'expected' => '<img src = "https://example.com/foo-300x225.jpg" srcset = "https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="auto, (max-width: 650px) 100vw, 650px" loading = "lazy">',
			),
			'expected_when_attributes_are_upper_case'      => array(
				'input'    => '<IMG SRC="https://example.com/foo-300x225.jpg" SRCSET="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" SIZES="(max-width: 650px) 100vw, 650px" LOADING="LAZY">',
				'expected' => '<IMG SRC="https://example.com/foo-300x225.jpg" SRCSET="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="auto, (max-width: 650px) 100vw, 650px" LOADING="LAZY">',
			),
			'expected_when_loading_lazy_lacks_quotes'      => array(
				'input'    => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 650px) 100vw, 650px" loading=lazy>',
				'expected' => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="auto, (max-width: 650px) 100vw, 650px" loading=lazy>',
			),
			'expected_when_loading_lazy_has_whitespace'    => array(
				'input'    => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 650px) 100vw, 650px" loading=" lazy ">',
				'expected' => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="auto, (max-width: 650px) 100vw, 650px" loading=" lazy ">',
			),
			'not_expected_when_sizes_auto_lacks_quotes'    => array(
				'input'    => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes=auto loading="lazy">',
				'expected' => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes=auto loading="lazy">',
			),
			'expected_when_auto_size_preceded_by_extra_commas' => array(
				'input'    => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes=",,,,,,,,,auto, (max-width: 650px) 100vw, 650px" loading="lazy">',
				'expected' => '<img src="https://example.com/foo-300x225.jpg" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="auto, ,,,,,,,,,auto, (max-width: 650px) 100vw, 650px" loading="lazy">',
			),
		);
	}

	/**
	 * @covers ::auto_sizes_update_content_img_tag
	 * @dataProvider data_provider_to_test_auto_sizes_update_content_img_tag
	 */
	public function test_auto_sizes_update_content_img_tag( string $input, string $expected ): void {
		$this->assertSame(
			$expected,
			auto_sizes_update_content_img_tag( $input )
		);
	}
}
