<?php
/**
 * Tests for transparency detection functionality in webp-uploads plugin.
 *
 * @package webp-uploads
 */

use WebP_Uploads\Tests\TestCase;

class Test_WebP_Uploads_Transparency extends TestCase {

	public function set_up(): void {
		parent::set_up();
		$this->set_image_output_type( 'avif' );
	}

	/**
	 * Tests webp_uploads_imagick_avif_transparency_supported returns expected value based on Imagick availability.
	 *
	 * @covers ::webp_uploads_imagick_avif_transparency_supported
	 */
	public function test_webp_uploads_imagick_avif_transparency_supported_without_imagick(): void {
		if ( extension_loaded( 'imagick' ) ) {
			$this->markTestSkipped( 'Test requires Imagick to not be loaded.' );
		}

		$this->assertFalse( webp_uploads_imagick_avif_transparency_supported() );
	}

	/**
	 * Tests webp_uploads_imagick_avif_transparency_supported checks version correctly.
	 *
	 * @covers ::webp_uploads_imagick_avif_transparency_supported
	 */
	public function test_webp_uploads_imagick_avif_transparency_supported_checks_version(): void {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			$this->markTestSkipped( 'Imagick extension is not available.' );
		}

		$result = webp_uploads_imagick_avif_transparency_supported();

		// Get the actual Imagick version to verify the function's result.
		$imagick_version = Imagick::getVersion();
		if ( (bool) preg_match( '/\d+(?:\.\d+)+(?:-\d+)?/', $imagick_version['versionString'], $matches ) ) {
			$version = $matches[0];
		} else {
			$version = $imagick_version['versionString'];
		}

		$expected = version_compare( $version, '7.0.25', '>=' );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Tests webp_uploads_check_image_transparency returns false when output format is not AVIF.
	 *
	 * @covers ::webp_uploads_check_image_transparency
	 */
	public function test_webp_uploads_check_image_transparency_returns_false_for_non_avif_format(): void {
		$this->set_image_output_type( 'webp' );

		$result = webp_uploads_check_image_transparency( TESTS_PLUGIN_DIR . '/tests/data/images/dice.png' );
		$this->assertFalse( $result );
	}

	/**
	 * Tests webp_uploads_check_image_transparency returns false when Imagick supports AVIF transparency.
	 *
	 * @covers ::webp_uploads_check_image_transparency
	 */
	public function test_webp_uploads_check_image_transparency_returns_false_when_imagick_supports_transparency(): void {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			$this->markTestSkipped( 'Imagick extension is not available.' );
		}

		if ( ! webp_uploads_imagick_avif_transparency_supported() ) {
			$this->markTestSkipped( 'Test requires Imagick with AVIF transparency support.' );
		}

		$this->set_image_output_type( 'avif' );

		$result = webp_uploads_check_image_transparency( TESTS_PLUGIN_DIR . '/tests/data/images/dice.png' );
		$this->assertFalse( $result );
	}

	/**
	 * Tests webp_uploads_check_image_transparency returns false when file does not exist.
	 *
	 * @covers ::webp_uploads_check_image_transparency
	 */
	public function test_webp_uploads_check_image_transparency_returns_false_for_nonexistent_file(): void {
		$this->set_image_output_type( 'avif' );

		$result = webp_uploads_check_image_transparency( '/nonexistent/path/image.png' );
		$this->assertFalse( $result );
	}

	/**
	 * Tests webp_uploads_check_image_transparency returns false when filename is null without current editor instance.
	 *
	 * @covers ::webp_uploads_check_image_transparency
	 */
	public function test_webp_uploads_check_image_transparency_returns_false_for_null_filename_without_instance(): void {
		$this->set_image_output_type( 'avif' );

		// Ensure no current instance is set.
		if ( class_exists( 'WebP_Uploads_Image_Editor_Imagick' ) ) {
			WebP_Uploads_Image_Editor_Imagick::$current_instance = null;
		}

		$result = webp_uploads_check_image_transparency( null );
		$this->assertFalse( $result );
	}

	/**
	 * Tests WebP_Uploads_Image_Editor_Imagick::has_transparency detects transparency in PNG.
	 *
	 * @covers WebP_Uploads_Image_Editor_Imagick::has_transparency
	 */
	public function test_has_transparency_detects_transparent_png(): void {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			$this->markTestSkipped( 'Imagick extension is not available.' );
		}

		$this->set_image_output_type( 'avif' );

		// Force loading of the extended editor class.
		$editors = webp_uploads_set_image_editors( array( 'WP_Image_Editor_Imagick' ) );

		if ( ! class_exists( 'WebP_Uploads_Image_Editor_Imagick' ) ) {
			$this->markTestSkipped( 'WebP_Uploads_Image_Editor_Imagick class is not available.' );
		}

		$editor = wp_get_image_editor( TESTS_PLUGIN_DIR . '/tests/data/images/dice.png' );

		if ( is_wp_error( $editor ) ) {
			$this->markTestSkipped( 'Could not create image editor: ' . $editor->get_error_message() );
		}

		if ( ! $editor instanceof WebP_Uploads_Image_Editor_Imagick ) {
			$this->markTestSkipped( 'Image editor is not WebP_Uploads_Image_Editor_Imagick.' );
		}

		$has_transparency = $editor->has_transparency();

		$this->assertNotInstanceOf( WP_Error::class, $has_transparency );
		$this->assertTrue( $has_transparency );
	}

	/**
	 * Tests WebP_Uploads_Image_Editor_Imagick::has_transparency returns false for JPEG (no transparency).
	 *
	 * @covers WebP_Uploads_Image_Editor_Imagick::has_transparency
	 */
	public function test_has_transparency_returns_false_for_jpeg(): void {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			$this->markTestSkipped( 'Imagick extension is not available.' );
		}

		$this->set_image_output_type( 'avif' );

		// Force loading of the extended editor class.
		$editors = webp_uploads_set_image_editors( array( 'WP_Image_Editor_Imagick' ) );

		if ( ! class_exists( 'WebP_Uploads_Image_Editor_Imagick' ) ) {
			$this->markTestSkipped( 'WebP_Uploads_Image_Editor_Imagick class is not available.' );
		}

		$editor = wp_get_image_editor( TESTS_PLUGIN_DIR . '/tests/data/images/car.jpeg' );

		if ( is_wp_error( $editor ) ) {
			$this->markTestSkipped( 'Could not create image editor: ' . $editor->get_error_message() );
		}

		if ( ! $editor instanceof WebP_Uploads_Image_Editor_Imagick ) {
			$this->markTestSkipped( 'Image editor is not WebP_Uploads_Image_Editor_Imagick.' );
		}

		$has_transparency = $editor->has_transparency();

		$this->assertNotInstanceOf( WP_Error::class, $has_transparency );
		$this->assertFalse( $has_transparency );
	}

	/**
	 * Tests WebP_Uploads_Image_Editor_Imagick::get_file returns correct file path.
	 *
	 * @covers WebP_Uploads_Image_Editor_Imagick::get_file
	 */
	public function test_get_file_returns_correct_path(): void {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			$this->markTestSkipped( 'Imagick extension is not available.' );
		}

		$this->set_image_output_type( 'avif' );

		// Force loading of the extended editor class.
		$editors = webp_uploads_set_image_editors( array( 'WP_Image_Editor_Imagick' ) );

		if ( ! class_exists( 'WebP_Uploads_Image_Editor_Imagick' ) ) {
			$this->markTestSkipped( 'WebP_Uploads_Image_Editor_Imagick class is not available.' );
		}

		$image_path = TESTS_PLUGIN_DIR . '/tests/data/images/dice.png';
		$editor     = wp_get_image_editor( $image_path );

		if ( is_wp_error( $editor ) ) {
			$this->markTestSkipped( 'Could not create image editor: ' . $editor->get_error_message() );
		}

		if ( ! $editor instanceof WebP_Uploads_Image_Editor_Imagick ) {
			$this->markTestSkipped( 'Image editor is not WebP_Uploads_Image_Editor_Imagick.' );
		}

		$this->assertSame( $image_path, $editor->get_file() );
	}

	/**
	 * Tests WebP_Uploads_Image_Editor_Imagick sets current_instance on load.
	 *
	 * @covers WebP_Uploads_Image_Editor_Imagick::load
	 */
	public function test_load_sets_current_instance(): void {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			$this->markTestSkipped( 'Imagick extension is not available.' );
		}

		$this->set_image_output_type( 'avif' );

		// Force loading of the extended editor class.
		$editors = webp_uploads_set_image_editors( array( 'WP_Image_Editor_Imagick' ) );

		if ( ! class_exists( 'WebP_Uploads_Image_Editor_Imagick' ) ) {
			$this->markTestSkipped( 'WebP_Uploads_Image_Editor_Imagick class is not available.' );
		}

		// Reset current instance.
		WebP_Uploads_Image_Editor_Imagick::$current_instance = null;

		$editor = wp_get_image_editor( TESTS_PLUGIN_DIR . '/tests/data/images/dice.png' );

		if ( is_wp_error( $editor ) ) {
			$this->markTestSkipped( 'Could not create image editor: ' . $editor->get_error_message() );
		}

		if ( ! $editor instanceof WebP_Uploads_Image_Editor_Imagick ) {
			$this->markTestSkipped( 'Image editor is not WebP_Uploads_Image_Editor_Imagick.' );
		}

		$this->assertNotNull( WebP_Uploads_Image_Editor_Imagick::$current_instance );
	}

	/**
	 * Tests webp_uploads_set_image_editors prepends custom editor when conditions are met.
	 *
	 * @covers ::webp_uploads_set_image_editors
	 */
	public function test_webp_uploads_set_image_editors_prepends_custom_editor(): void {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			$this->markTestSkipped( 'Imagick extension is not available.' );
		}

		$this->set_image_output_type( 'avif' );

		// Only run this test if Imagick doesn't support AVIF transparency.
		if ( webp_uploads_imagick_avif_transparency_supported() ) {
			$this->markTestSkipped( 'Test requires Imagick without AVIF transparency support.' );
		}

		$editors = webp_uploads_set_image_editors( array( 'WP_Image_Editor_Imagick' ) );

		$this->assertContains( 'WebP_Uploads_Image_Editor_Imagick', $editors );
		$this->assertSame( 'WebP_Uploads_Image_Editor_Imagick', $editors[0] );
	}

	/**
	 * Tests webp_uploads_set_image_editors returns original editors when output format is not AVIF.
	 *
	 * @covers ::webp_uploads_set_image_editors
	 */
	public function test_webp_uploads_set_image_editors_returns_original_for_non_avif(): void {
		$this->set_image_output_type( 'webp' );

		$original_editors = array( 'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD' );
		$editors          = webp_uploads_set_image_editors( $original_editors );

		$this->assertSame( $original_editors, $editors );
	}

	/**
	 * Tests webp_uploads_set_image_editors returns original editors when Imagick supports AVIF transparency.
	 *
	 * @covers ::webp_uploads_set_image_editors
	 */
	public function test_webp_uploads_set_image_editors_returns_original_when_transparency_supported(): void {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			$this->markTestSkipped( 'Imagick extension is not available.' );
		}

		if ( ! webp_uploads_imagick_avif_transparency_supported() ) {
			$this->markTestSkipped( 'Test requires Imagick with AVIF transparency support.' );
		}

		$this->set_image_output_type( 'avif' );

		$original_editors = array( 'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD' );
		$editors          = webp_uploads_set_image_editors( $original_editors );

		$this->assertSame( $original_editors, $editors );
	}

	/**
	 * Tests webp_uploads_set_image_editors returns original editors when array is empty.
	 *
	 * @covers ::webp_uploads_set_image_editors
	 */
	public function test_webp_uploads_set_image_editors_returns_original_for_empty_array(): void {
		$this->set_image_output_type( 'avif' );

		$editors = webp_uploads_set_image_editors( array() );

		$this->assertSame( array(), $editors );
	}

	/**
	 * Tests webp_uploads_set_image_editors returns original editors when first editor is not Imagick.
	 *
	 * @covers ::webp_uploads_set_image_editors
	 */
	public function test_webp_uploads_set_image_editors_returns_original_when_first_not_imagick(): void {
		$this->set_image_output_type( 'avif' );

		$original_editors = array( 'WP_Image_Editor_GD', 'WP_Image_Editor_Imagick' );
		$editors          = webp_uploads_set_image_editors( $original_editors );

		$this->assertSame( $original_editors, $editors );
	}

	/**
	 * Tests that transparency check falls back to WebP for transparent PNG images.
	 *
	 * @covers ::webp_uploads_get_upload_image_mime_transforms
	 */
	public function test_upload_image_mime_transforms_fallback_to_webp_for_transparent_png(): void {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			$this->markTestSkipped( 'Imagick extension is not available.' );
		}

		// Only test when Imagick doesn't support AVIF transparency.
		if ( webp_uploads_imagick_avif_transparency_supported() ) {
			$this->markTestSkipped( 'Test requires Imagick without AVIF transparency support.' );
		}

		if ( ! class_exists( 'WebP_Uploads_Image_Editor_Imagick' ) ) {
			// Load the class by triggering the filter.
			webp_uploads_set_image_editors( array( 'WP_Image_Editor_Imagick' ) );
		}

		if ( ! class_exists( 'WebP_Uploads_Image_Editor_Imagick' ) ) {
			$this->markTestSkipped( 'WebP_Uploads_Image_Editor_Imagick class is not available.' );
		}

		$this->set_image_output_type( 'avif' );

		$transparent_image = TESTS_PLUGIN_DIR . '/tests/data/images/dice.png';
		$transforms        = webp_uploads_get_upload_image_mime_transforms( $transparent_image );

		// For transparent images, should fall back to WebP.
		$this->assertArrayHasKey( 'image/png', $transforms );
		$this->assertContains( 'image/webp', $transforms['image/png'] );
	}

	/**
	 * Tests site health function returns correct structure.
	 *
	 * @covers ::webp_uploads_imagick_avif_transparency_supported_test
	 */
	public function test_site_health_imagick_avif_transparency_test_returns_correct_structure(): void {
		$result = webp_uploads_imagick_avif_transparency_supported_test();

		$this->assertArrayHasKey( 'label', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'badge', $result );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertArrayHasKey( 'actions', $result );
		$this->assertArrayHasKey( 'test', $result );

		$this->assertArrayHasKey( 'label', $result['badge'] );
		$this->assertArrayHasKey( 'color', $result['badge'] );
	}

	/**
	 * Tests site health function returns good status when transparency is supported.
	 *
	 * @covers ::webp_uploads_imagick_avif_transparency_supported_test
	 */
	public function test_site_health_returns_good_status_when_supported(): void {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			$this->markTestSkipped( 'Imagick extension is not available.' );
		}

		if ( ! webp_uploads_imagick_avif_transparency_supported() ) {
			$this->markTestSkipped( 'Test requires Imagick with AVIF transparency support.' );
		}

		$result = webp_uploads_imagick_avif_transparency_supported_test();

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'supports AVIF', $result['label'] );
	}

	/**
	 * Tests site health function returns recommended status when transparency is not supported.
	 *
	 * @covers ::webp_uploads_imagick_avif_transparency_supported_test
	 */
	public function test_site_health_returns_recommended_status_when_not_supported(): void {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			$this->markTestSkipped( 'Imagick extension is not available.' );
		}

		if ( webp_uploads_imagick_avif_transparency_supported() ) {
			$this->markTestSkipped( 'Test requires Imagick without AVIF transparency support.' );
		}

		$result = webp_uploads_imagick_avif_transparency_supported_test();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'does not support', $result['label'] );
	}

	/**
	 * Tests has_transparency caches results for same file.
	 *
	 * @covers WebP_Uploads_Image_Editor_Imagick::has_transparency
	 */
	public function test_has_transparency_caches_results(): void {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			$this->markTestSkipped( 'Imagick extension is not available.' );
		}

		$this->set_image_output_type( 'avif' );

		// Force loading of the extended editor class.
		$editors = webp_uploads_set_image_editors( array( 'WP_Image_Editor_Imagick' ) );

		if ( ! class_exists( 'WebP_Uploads_Image_Editor_Imagick' ) ) {
			$this->markTestSkipped( 'WebP_Uploads_Image_Editor_Imagick class is not available.' );
		}

		$editor = wp_get_image_editor( TESTS_PLUGIN_DIR . '/tests/data/images/dice.png' );

		if ( is_wp_error( $editor ) ) {
			$this->markTestSkipped( 'Could not create image editor: ' . $editor->get_error_message() );
		}

		if ( ! $editor instanceof WebP_Uploads_Image_Editor_Imagick ) {
			$this->markTestSkipped( 'Image editor is not WebP_Uploads_Image_Editor_Imagick.' );
		}

		// Call has_transparency twice - second call should use cache.
		$result1 = $editor->has_transparency();
		$result2 = $editor->has_transparency();

		$this->assertSame( $result1, $result2 );
		$this->assertTrue( $result1 );
	}
}
