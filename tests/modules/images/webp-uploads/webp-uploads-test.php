<?php
/**
 * Tests for webp-uploads module.
 *
 * @package performance-lab
 * @group   webp-uploads
 */

class WebP_Uploads_Tests extends WP_UnitTestCase {

	/**
	 * Test if webp-uploads applies filter based on system support of WebP.
	 */
	public function test_webp_uploads_applies_filter_based_on_system_support() {
		// Set the expected value based on the underlying system support for WebP.
		if ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$expect = array( 'image/jpeg' => 'image/webp' );
		} else {
			$expect = array();
		}

		add_filter(
			'webp_uploads_images_with_multiple_mime_types',
			function () {
				return array(
					'image/webp' => 'webp'
				);
			}
		);

		// Simulate the plugins loaded hook to triggering filtering.
		do_action( 'plugins_loaded' );

		$output_format = apply_filters( 'image_editor_output_format', array(), '', 'image/jpeg' );
		$this->assertEquals( $expect, $output_format );
	}

	/**
	 * Verify webp-uploads applies filter with a system that supports WebP.
	 */
	public function test_webp_uploads_filter_image_editor_output_format_with_support() {
		// Mock a system that supports WebP.
		add_filter( 'wp_image_editors', array( $this, 'mock_wp_image_editor_supports' ) );
		$output_format = webp_uploads_filter_image_editor_output_format( array(), '', 'image/jpeg' );
		$this->assertEquals( array( 'image/jpeg' => 'image/webp' ), $output_format );
	}

	/**
	 * Verify if webp-uploads doesn't apply filter with a system that doesn't support WebP.
	 */
	public function test_webp_uploads_filter_image_editor_output_format_without_support() {
		// Mock a system that doesn't support WebP.
		add_filter( 'wp_image_editors', array( $this, 'mock_wp_image_editor_doesnt_support' ) );
		$output_format = webp_uploads_filter_image_editor_output_format( array(), '', 'image/jpeg' );
		$this->assertEquals( array(), $output_format );
	}

	/**
	 * Mock an image editor that supports WebP.
	 */
	public function mock_wp_image_editor_supports() {
		return array( 'WP_Image_Supports_WebP' );
	}

	/**
	 * Mock an image editor that doesn't support WebP.
	 */
	public function mock_wp_image_editor_doesnt_support() {
		return array( 'WP_Image_Doesnt_Support_WebP' );
	}

	/**
	 * Test that the webp_uploads_filter_image_editor_output_format callback
	 * properly catches filenames ending in `-scaled.{extension}.`.
	 *
	 * @dataProvider data_provider_webp_uploads_filter_image_editor_output_format_with_scaled_filename
	 */
	public function test_webp_uploads_filter_image_editor_output_format_with_scaled_filename( $filename, $mime_type, $expect ) {
		// Mock a system that supports WebP.
		add_filter( 'wp_image_editors', array( $this, 'mock_wp_image_editor_supports' ) );
		$output_format = webp_uploads_filter_image_editor_output_format( array(), $filename, $mime_type );
		$this->assertEquals( $expect, $output_format );
	}

	/**
	 * Data provider for test_webp_uploads_filter_image_editor_output_format_with_scaled_filename.
	 */
	public function data_provider_webp_uploads_filter_image_editor_output_format_with_scaled_filename() {
		return array(
			// Jpeg images are converted to WebP by default.
			array( 'image.jpg', 'image/jpeg', array( 'image/jpeg' => 'image/webp' ) ),
			array( 'another-test-image.jpg', 'image/jpeg', array( 'image/jpeg' => 'image/webp' ) ),
			array( 'previously-scaled-image.jpg', 'image/jpeg', array( 'image/jpeg' => 'image/webp' ) ),

			// Images with filenames ending in `-scaled.{extension}.` are not converted to WebP.
			array( 'image-scaled.jpg', 'image/jpeg', array() ),
			array( 'image-scaled.jpeg', 'image/jpeg', array() ),

			// Non jpeg images are not converted to WebP.
			array( 'image.png', 'image/png', array() ),
			array( 'image-scaled.png', 'image/png', array() ),
		);
	}

	/**
	 * add filter to create additional mime types by default
	 *
	 * @test
	 */
	function it_should_add_filter_to_create_additional_mime_types_by_default() {
		// Simulate the plugins loaded hook to triggering filtering.
		do_action( 'plugins_loaded' );

		$this->assertSame( 10, has_filter( 'wp_generate_attachment_metadata', 'webp_uploads_create_images_with_additional_mime_types' ) );
		$this->assertFalse( has_filter( 'image_editor_output_format', 'webp_uploads_filter_image_editor_output_format'  ) );
	}

	/**
	 * disable additional mime generation if only a single image format is valid
	 *
	 * @test
	 */
	function it_should_disable_additional_mime_generation_if_only_a_single_image_format_is_valid() {
		add_filter(
			'webp_uploads_images_with_multiple_mime_types',
			function () {
				return array(
					'image/webp' => 'webp'
				);
			}
		);

		// Simulate the plugins loaded hook to triggering filtering.
		do_action( 'plugins_loaded' );

		$this->assertFalse( has_filter( 'wp_generate_attachment_metadata', 'webp_uploads_create_images_with_additional_mime_types' ) );
		$this->assertSame( 10, has_filter( 'image_editor_output_format', 'webp_uploads_filter_image_editor_output_format'  ) );
	}

	/**
	 * not registered a webp conversion if only JPEG is supported
	 *
	 * @test
	 */
	function it_should_not_registered_a_webp_conversion_if_only_jpeg_is_supported() {
		add_filter(
			'webp_uploads_images_with_multiple_mime_types',
			function () {
				return array(
					'image/jpeg' => 'jpg'
				);
			}
		);

		// Simulate the plugins loaded hook to triggering filtering.
		do_action( 'plugins_loaded' );

		$this->assertFalse( has_filter( 'wp_generate_attachment_metadata', 'webp_uploads_create_images_with_additional_mime_types' ) );
		$this->assertFalse( has_filter( 'image_editor_output_format', 'webp_uploads_filter_image_editor_output_format' ) );
	}

	/**
	 * return the registerd image sizes by default
	 *
	 * @test
	 */
	function it_should_return_the_registerd_image_sizes_by_default() {
		$expected = array(
			'thumbnail' => array(
				'width'  => 150,
				'height' => 150,
				'crop'   => true,
			),
			'medium' => array(
				'width'  => 300,
				'height' => 300,
				'crop'   => false,
			),
			'medium_large' => array(
				'width'  => 768,
				'height' => 0,
				'crop'   => false,
			),
			'large' => array(
				'width'  => 1024,
				'height' => 1024,
				'crop'   => false,
			),
			'1536x1536' => array(
				'width'  => 1536,
				'height' => 1536,
				'crop'   => false,
			),
			'2048x2048' => array(
				'width'  => 2048,
				'height' => 2048,
				'crop'   => false,
			),
		);
		$this->assertEquals( $expected, webp_uploads_get_image_sizes() );
	}

	/**
	 * select the remaining mime types
	 *
	 * @dataProvider provider_remaining_mime_types
	 *
	 * @test
	 */
	public function it_should_select_the_remaining_mime_types( array $expected, array $metadata, $size ) {
		$this->assertEquals( $expected, webp_uploads_get_remaining_image_mimes( $metadata, $size ) );
	}

	public function provider_remaining_mime_types() {
		$metadata = array(
			'sizes' => array(
				'thumbnail' => array(
					'width'     => 150,
					'height'    => 150,
					'file'      => ' YspbwVyNkys-unsplash.jpg',
					'mime-type' => 'image/jpeg'
				)
			)
		);

		yield 'not existing image size' => array(
			array(),
			$metadata,
			'full-size'
		);

		yield 'image present with invalid format' => array(
			array(),
			array_merge( $metadata, array( 'sizes' => array( 'full-size' => 10 ) ) ),
			'full-size',
		);

		yield 'image present without a defined mime property and file property' => array(
			array(),
			array_merge( $metadata, array( 'sizes' => array( 'full-size' => array() ) ) ),
			'full-size',
		);

		yield 'image present without a defined mime property' => array(
			array(),
			array_merge( $metadata, array( 'sizes' => array( 'full-size' => array( 'file' => '' ) ) ) ),
			'full-size',
		);

		yield 'WebP mime type is returned if the provided image is a JPEG' => array(
			array(
				'image/webp' => 'webp'
			),
			$metadata,
			'thumbnail',
		);

		yield 'JPEG mime type is returned if the provided image is a WebP' => array(
			array(
				'image/jpeg' => 'jpg'
			),
			array_merge(
				$metadata,
				array(
					'sizes' => array(
						'fullsize' => array(
							'width'     => 1024,
							'height'    => 600,
							'file'      => ' YspbwVyNkys-unsplash.webp',
							'mime-type' => 'image/webp'
						)
					)
				)
			),
			'fullsize',
		);

		yield 'Not defined valid mime type is used' => array(
			array(),
			array_merge(
				$metadata,
				array(
					'sizes' => array(
						'fullsize' => array(
							'width'     => 1024,
							'height'    => 600,
							'file'      => ' YspbwVyNkys-unsplash.avif',
							'mime-type' => 'image/avif'
						)
					)
				)
			),
			'fullsize',
		);
	}

	/**
	 * generate a hash name for the provided input
	 *
	 * @dataProvider provider_file_names_to_extract_hash
	 *
	 * @test
	 */
	public function it_should_generate_a_hash_name_for_the_provided_input( $expected, $filename ) {
		$this->assertSame( $expected, webp_uploads_get_hash_from_edited_file( $filename ) );
	}

	public function provider_file_names_to_extract_hash() {
		yield 'empty file name' => array(
			'',
			'',
		);

		yield 'normal file name' => array(
			'',
			'sq-he-ZpjeP4MsF5U-unsplash-768x509.webp',
		);

		yield 'edited file name with not enough digits' => array(
			'',
			'sq-he-ZpjeP4MsF5U-unsplash-e123456789-768x509.webp',
		);

		yield 'edited file name with valid hash before the extension' => array(
			'',
			'YspbwVyNkys-unsplash-e1645031517383-768x509.jpg',
		);

		yield 'edited file name with valid hash' => array(
			'1645031517383',
			'YspbwVyNkys-unsplash-e1645031517383.jpg',
		);
	}
}
