<?php
/**
 * Tests for transparency detection functionality in webp-uploads plugin.
 *
 * @package webp-uploads
 */

use WebP_Uploads\Tests\TestCase;

class Test_WebP_Uploads_Transparency extends TestCase {

	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) || ! class_exists( 'ImagickException' ) ) {
			self::markTestSkipped( 'Imagick extension is not available.' );
		}

		// Check if Imagick supports AVIF encoding.
		try {
			$i = new Imagick();
			$i->newImage( 10, 10, 'white' );
			$i->setImageFormat( 'avif' );
			$i->getImageBlob();
		} catch ( ImagickException $e ) {
			self::markTestSkipped( 'Imagick does not support AVIF encoding.' );
		}
	}

	public function set_up(): void {
		parent::set_up();
		$this->set_image_output_type( 'avif' );
	}

	/**
	 * Data provider for ImageMagick version strings.
	 *
	 * @return array<string, array{string, bool}> Test data with version strings and expected support.
	 */
	public function data_provider_imagick_versions(): array {
		return array(
			'ImageMagick 6.8.9 Q16 x86_64'               => array( 'ImageMagick 6.8.9-9 Q16 x86_64 2018-09-28 https://imagemagick.org/index.php', false ),
			'ImageMagick 6.9.12 Q16 x86_64'              => array( 'ImageMagick 6.9.12-27 Q16 x86_64 2021-10-24 https://imagemagick.org', false ),
			'ImageMagick 7.1.0 Q16-HDRI x86_64'          => array( 'ImageMagick 7.1.0-57 Q16-HDRI x86_64 d68553b17:20221230 https://imagemagick.org', true ),
			'ImageMagick 7.1.2 Q16-HDRI x86_64'          => array( 'ImageMagick 7.1.2-7 Q16-HDRI x86_64 23405 https://imagemagick.org', true ),
			'ImageMagick 6.9.13 Q16 x86_64'              => array( 'ImageMagick 6.9.13-17 Q16 x86_64', false ),
			'ImageMagick 7.1.1 Q16 aarch64'              => array( 'ImageMagick 7.1.1-15 Q16 aarch64 98eceff6a:20230729 https://imagemagick.org', true ),
			'ImageMagick 7.0.25 (exact minimum version)' => array( 'ImageMagick 7.0.25 Q16 x86_64', true ),
			'ImageMagick 7.0.24 (just below minimum)'    => array( 'ImageMagick 7.0.24 Q16 x86_64', false ),
			'Empty string should return false'           => array( '', false ),
			'Invalid string without version should be false' => array( 'Invalid version string', false ),
			'String with only text should be false'      => array( 'ImageMagick', false ),
			'Malformed version string should be false'   => array( 'ImageMagick x.y.z', false ),
		);
	}

	/**
	 * Tests webp_uploads_imagick_avif_transparency_supported checks version correctly.
	 *
	 * @dataProvider data_provider_imagick_versions
	 * @covers ::webp_uploads_imagick_avif_transparency_supported
	 *
	 * @param string $version          ImageMagick version string.
	 * @param bool   $expected_support Expected transparency support result.
	 */
	public function test_webp_uploads_imagick_avif_transparency_supported_checks_version( string $version, bool $expected_support ): void {
		remove_all_filters( 'webp_uploads_imagick_avif_transparency_supported' );

		$result = webp_uploads_imagick_avif_transparency_supported( $version );

		$this->assertSame( $expected_support, $result );
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
		$this->mock_avif_transparency_support( true );

		$result = webp_uploads_check_image_transparency( TESTS_PLUGIN_DIR . '/tests/data/images/dice.png' );
		$this->assertFalse( $result );
	}

	/**
	 * Tests webp_uploads_check_image_transparency returns false when file does not exist.
	 *
	 * @covers ::webp_uploads_check_image_transparency
	 */
	public function test_webp_uploads_check_image_transparency_returns_false_for_nonexistent_file(): void {
		$result = webp_uploads_check_image_transparency( '/nonexistent/path/image.png' );
		$this->assertFalse( $result );
	}

	/**
	 * Tests webp_uploads_check_image_transparency returns false when filename is null without current editor instance.
	 *
	 * @covers ::webp_uploads_check_image_transparency
	 */
	public function test_webp_uploads_check_image_transparency_returns_false_for_null_filename_without_instance(): void {
		// Ensure no current instance is set.
		if ( class_exists( 'WebP_Uploads_Image_Editor_Imagick' ) ) {
			WebP_Uploads_Image_Editor_Imagick::$current_instance = null;
		}

		$result = webp_uploads_check_image_transparency( null );
		$this->assertFalse( $result );
	}

	/**
	 * Tests WebP_Uploads_Image_Editor_Imagick::get_file returns correct file path.
	 *
	 * @covers WebP_Uploads_Image_Editor_Imagick::get_file
	 */
	public function test_get_file_returns_correct_path(): void {
		$this->setup_custom_image_editor( false );

		$image_path = TESTS_PLUGIN_DIR . '/tests/data/images/dice.png';
		$editor     = wp_get_image_editor( $image_path );

		$this->assertNotWPError( $editor, 'Failed to create image editor.' );
		// @phpstan-ignore-next-line Class extends runtime alias WebP_Uploads_Image_Editor_Imagick_Base.
		$this->assertInstanceOf( WebP_Uploads_Image_Editor_Imagick::class, $editor, 'Editor is not the custom image editor class.' );
		$this->assertSame( $image_path, $editor->get_file() );
	}

	/**
	 * Tests WebP_Uploads_Image_Editor_Imagick sets current_instance on load.
	 *
	 * @covers WebP_Uploads_Image_Editor_Imagick::load
	 */
	public function test_load_sets_current_instance(): void {
		$this->setup_custom_image_editor( false );

		// Reset current instance.
		WebP_Uploads_Image_Editor_Imagick::$current_instance = null;

		$editor = wp_get_image_editor( TESTS_PLUGIN_DIR . '/tests/data/images/dice.png' );

		$this->assertNotWPError( $editor, 'Failed to create image editor.' );
		// @phpstan-ignore-next-line Class extends runtime alias WebP_Uploads_Image_Editor_Imagick_Base.
		$this->assertInstanceOf( WebP_Uploads_Image_Editor_Imagick::class, $editor, 'Editor is not the custom image editor class.' );
		$this->assertNotNull( WebP_Uploads_Image_Editor_Imagick::$current_instance );
	}

	/**
	 * Tests webp_uploads_set_image_editors prepends custom editor when conditions are met.
	 *
	 * @covers ::webp_uploads_set_image_editors
	 */
	public function test_webp_uploads_set_image_editors_prepends_custom_editor(): void {
		$this->mock_avif_transparency_support( false );

		$editors = webp_uploads_set_image_editors( array( 'WP_Image_Editor_Imagick' ) );

		$this->assertContains( 'WebP_Uploads_Image_Editor_Imagick', $editors );
		$this->assertSame( 'WebP_Uploads_Image_Editor_Imagick', $editors[0] );
	}

	/**
	 * Tests webp_uploads_set_image_editors returns original editors when Imagick supports AVIF transparency.
	 *
	 * @covers ::webp_uploads_set_image_editors
	 */
	public function test_webp_uploads_set_image_editors_returns_original_when_transparency_supported(): void {
		$this->mock_avif_transparency_support( true );

		$original_editors = array( 'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD' );
		$editors          = webp_uploads_set_image_editors( $original_editors );

		$this->assertSame( $original_editors, $editors );
	}

	/**
	 * Tests that transparency check falls back to WebP for transparent PNG images.
	 *
	 * @covers ::webp_uploads_get_upload_image_mime_transforms
	 */
	public function test_upload_image_mime_transforms_fallback_to_webp_for_transparent_png(): void {
		$this->mock_avif_transparency_support( false );

		$this->ensure_custom_editor_loaded();

		// Load the class WebP_Uploads_Image_Editor_Imagick by triggering the filter.
		webp_uploads_set_image_editors( array( 'WP_Image_Editor_Imagick' ) );

		$this->assertTrue( class_exists( 'WebP_Uploads_Image_Editor_Imagick' ), 'Custom image editor class should be loaded.' );

		$transparent_image = TESTS_PLUGIN_DIR . '/tests/data/images/dice.png';
		$transforms        = webp_uploads_get_upload_image_mime_transforms( $transparent_image );

		// For transparent images, should fall back to WebP.
		$this->assertArrayHasKey( 'image/png', $transforms );
		$this->assertContains( 'image/webp', $transforms['image/png'] );
	}

	/**
	 * Tests webp_uploads_get_upload_image_mime_transforms returns AVIF for non-transparent images.
	 *
	 * @covers ::webp_uploads_get_upload_image_mime_transforms
	 */
	public function test_upload_image_mime_transforms_uses_avif_for_non_transparent_images(): void {
		if ( ! webp_uploads_mime_type_supported( 'image/avif' ) ) {
			$this->markTestSkipped( 'AVIF is not supported.' );
		}

		$this->mock_avif_transparency_support( false );

		$non_transparent_image = TESTS_PLUGIN_DIR . '/tests/data/images/car.jpeg';
		$transforms            = webp_uploads_get_upload_image_mime_transforms( $non_transparent_image );

		// For non-transparent images, should use AVIF.
		$this->assertArrayHasKey( 'image/jpeg', $transforms );
		$this->assertContains( 'image/avif', $transforms['image/jpeg'] );
	}

	/**
	 * Tests webp_uploads_get_upload_image_mime_transforms with WebP output format ignores transparency.
	 *
	 * @covers ::webp_uploads_get_upload_image_mime_transforms
	 */
	public function test_upload_image_mime_transforms_ignores_transparency_for_webp_output(): void {
		$this->set_image_output_type( 'webp' );

		$transparent_image = TESTS_PLUGIN_DIR . '/tests/data/images/dice.png';
		$transforms        = webp_uploads_get_upload_image_mime_transforms( $transparent_image );

		// WebP output should not check transparency.
		$this->assertArrayHasKey( 'image/png', $transforms );
		$this->assertContains( 'image/webp', $transforms['image/png'] );
	}

	/**
	 * Tests site health function returns good status when transparency is supported.
	 *
	 * @covers ::webp_uploads_imagick_avif_transparency_supported_test
	 */
	public function test_site_health_returns_good_status_when_supported(): void {
		$this->mock_avif_transparency_support( true );

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
		$this->mock_avif_transparency_support( false );

		$result = webp_uploads_imagick_avif_transparency_supported_test();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'does not support', $result['label'] );
	}

	/**
	 * Tests webp_uploads_check_image_transparency caches results for same file.
	 *
	 * @covers ::webp_uploads_check_image_transparency
	 */
	public function test_webp_uploads_check_image_transparency_caches_results(): void {
		$this->mock_avif_transparency_support( false );

		$this->ensure_custom_editor_loaded();

		// Force loading of the extended editor class.
		webp_uploads_set_image_editors( array( 'WP_Image_Editor_Imagick' ) );

		$this->assertTrue( class_exists( 'WebP_Uploads_Image_Editor_Imagick' ), 'Custom image editor class should be loaded.' );

		$image_path = TESTS_PLUGIN_DIR . '/tests/data/images/dice.png';

		// Call the function twice - second call should use cache.
		$result1 = webp_uploads_check_image_transparency( $image_path );
		$result2 = webp_uploads_check_image_transparency( $image_path );

		$this->assertSame( $result1, $result2 );
		$this->assertTrue( $result1 );
	}

	/**
	 * Tests webp_uploads_check_image_transparency when editor cannot be instantiated.
	 *
	 * @covers ::webp_uploads_check_image_transparency
	 */
	public function test_webp_uploads_check_image_transparency_when_editor_fails(): void {
		$this->mock_avif_transparency_support( false );

		$this->ensure_custom_editor_loaded();

		webp_uploads_set_image_editors( array( 'WP_Image_Editor_Imagick' ) );

		// Temporarily disable image editors to make wp_get_image_editor fail.
		add_filter( 'wp_image_editors', '__return_empty_array' );

		$image_path = TESTS_PLUGIN_DIR . '/tests/data/images/earth.gif';
		$result     = webp_uploads_check_image_transparency( $image_path );

		remove_filter( 'wp_image_editors', '__return_empty_array' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests webp_uploads_check_image_transparency with null filename and valid current instance.
	 *
	 * @covers ::webp_uploads_check_image_transparency
	 */
	public function test_webp_uploads_check_image_transparency_with_null_filename_and_current_instance(): void {
		$this->mock_avif_transparency_support( false );

		$this->ensure_custom_editor_loaded();

		webp_uploads_set_image_editors( array( 'WP_Image_Editor_Imagick' ) );

		$this->assertTrue( class_exists( 'WebP_Uploads_Image_Editor_Imagick' ), 'Custom image editor class should be loaded.' );

		$editor = wp_get_image_editor( TESTS_PLUGIN_DIR . '/tests/data/images/dice.png' );
		$this->assertNotWPError( $editor );

		// Now call with null filename - should use current instance's file.
		$result = webp_uploads_check_image_transparency( null );

		$this->assertTrue( $result );
	}

	/**
	 * Tests webp_uploads_check_image_transparency returns false when current instance file is empty.
	 *
	 * @covers ::webp_uploads_check_image_transparency
	 */
	public function test_webp_uploads_check_image_transparency_with_empty_current_instance_file(): void {
		$this->mock_avif_transparency_support( false );

		webp_uploads_set_image_editors( array( 'WP_Image_Editor_Imagick' ) );

		$this->assertTrue( class_exists( 'WebP_Uploads_Image_Editor_Imagick' ), 'Custom image editor class should be loaded.' );

		// Create a mock editor with empty file property.
		$editor = $this->getMockBuilder( 'WebP_Uploads_Image_Editor_Imagick' )
			->disableOriginalConstructor()
			->getMock();

		$editor->method( 'get_file' )->willReturn( '' );

		WebP_Uploads_Image_Editor_Imagick::$current_instance = $editor;

		$result = webp_uploads_check_image_transparency( null );

		// Clean up.
		WebP_Uploads_Image_Editor_Imagick::$current_instance = null;

		$this->assertFalse( $result );
	}

	/**
	 * Tests editor methods return expected values when properties are not set.
	 *
	 * @covers WebP_Uploads_Image_Editor_Imagick::has_transparency
	 * @covers WebP_Uploads_Image_Editor_Imagick::get_file
	 */
	public function test_editor_methods_with_unset_properties(): void {
		$this->mock_avif_transparency_support( false );

		webp_uploads_set_image_editors( array( 'WP_Image_Editor_Imagick' ) );

		$this->assertTrue( class_exists( 'WebP_Uploads_Image_Editor_Imagick' ), 'Custom image editor class should be loaded.' );

		$test_file = TESTS_PLUGIN_DIR . '/tests/data/images/dice.png';
		// @phpstan-ignore-next-line Constructor inherited from parent class.
		$editor = new WebP_Uploads_Image_Editor_Imagick( $test_file );
		$editor->load();
		$reflection = new ReflectionClass( $editor );

		// Test has_transparency() with null image property.
		$image_prop = $reflection->getProperty( 'image' );
		$image_prop->setAccessible( true );
		$image_prop->setValue( $editor, null );

		$result = $editor->has_transparency();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'image_editor_has_transparency_error_no_image', $result->get_error_code() );

		// Test get_file() with empty file property.
		$file_prop = $reflection->getProperty( 'file' );
		$file_prop->setAccessible( true );
		$file_prop->setValue( $editor, '' );

		$this->assertSame( '', $editor->get_file() );
	}

	/**
	 * Tests webp_uploads_add_imagick_avif_transparency_supported_test adds test to site health.
	 *
	 * @covers ::webp_uploads_add_imagick_avif_transparency_supported_test
	 */
	public function test_webp_uploads_add_imagick_avif_transparency_supported_test_adds_test(): void {
		$tests = array(
			'direct' => array(),
		);

		$result = webp_uploads_add_imagick_avif_transparency_supported_test( $tests );

		$this->assertArrayHasKey( 'direct', $result );
		$this->assertArrayHasKey( 'imagick_avif_transparency_supported', $result['direct'] );
		$this->assertArrayHasKey( 'label', $result['direct']['imagick_avif_transparency_supported'] );
		$this->assertArrayHasKey( 'test', $result['direct']['imagick_avif_transparency_supported'] );
		$this->assertSame( 'webp_uploads_imagick_avif_transparency_supported_test', $result['direct']['imagick_avif_transparency_supported']['test'] );
	}

	/**
	 * Tests webp_uploads_add_imagick_avif_transparency_supported_test preserves existing tests.
	 *
	 * @covers ::webp_uploads_add_imagick_avif_transparency_supported_test
	 */
	public function test_webp_uploads_add_imagick_avif_transparency_supported_test_preserves_existing_tests(): void {
		$tests = array(
			'direct' => array(
				'existing_test' => array(
					'label' => 'Existing Test',
					'test'  => 'existing_test_callback',
				),
			),
		);

		$result = webp_uploads_add_imagick_avif_transparency_supported_test( $tests );

		$this->assertArrayHasKey( 'existing_test', $result['direct'] );
		$this->assertArrayHasKey( 'imagick_avif_transparency_supported', $result['direct'] );
	}

	/**
	 * Tests integration: upload transparent PNG with AVIF output falls back to WebP.
	 *
	 * @covers ::webp_uploads_create_sources_property
	 * @covers ::webp_uploads_get_upload_image_mime_transforms
	 * @covers ::webp_uploads_check_image_transparency
	 */
	public function test_upload_transparent_png_with_avif_output_uses_webp(): void {
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$this->markTestSkipped( 'Mime type image/webp is not supported.' );
		}

		$this->mock_avif_transparency_support( false );
		update_option( 'perflab_generate_webp_and_jpeg', '1' );

		// Ensure the custom editor class is loaded.
		$this->ensure_custom_editor_loaded();

		// Set up the image editors filter.
		add_filter(
			'wp_image_editors',
			static function ( $editors ) {
				return webp_uploads_set_image_editors( $editors );
			}
		);

		$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/dice.png' );
		$metadata      = wp_get_attachment_metadata( $attachment_id );

		$this->assertIsArray( $metadata );
		$this->assertArrayHasKey( 'sources', $metadata );

		// Should have PNG and WebP sources, not AVIF due to transparency.
		$this->assertArrayHasKey( 'image/png', $metadata['sources'] );
		$this->assertArrayHasKey( 'image/webp', $metadata['sources'] );
	}

	/**
	 * Tests webp_uploads_set_image_editors handles final class gracefully.
	 *
	 * @covers ::webp_uploads_set_image_editors
	 */
	public function test_webp_uploads_set_image_editors_with_final_class(): void {
		$this->mock_avif_transparency_support( false );

		// Ensure WordPress's base image editor classes are loaded.
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';

		if ( ! class_exists( 'WP_Image_Editor_Imagick' ) ) {
			$this->markTestSkipped( 'WP_Image_Editor_Imagick class is not available.' );
		}

		require TESTS_PLUGIN_DIR . '/tests/data/class-final-test-image-editor.php';

		if ( ! class_exists( 'Final_Test_Image_Editor' ) ) {
			$this->markTestSkipped( 'Final_Test_Image_Editor class could not be loaded.' );
		}

		$editors = webp_uploads_set_image_editors( array( 'Final_Test_Image_Editor' ) );

		// Should return original editors array when first editor is final.
		$this->assertSame( array( 'Final_Test_Image_Editor' ), $editors );
	}

	/**
	 * Tests webp_uploads_set_image_editors creates class_alias for subclass.
	 *
	 * @covers ::webp_uploads_set_image_editors
	 */
	public function test_webp_uploads_set_image_editors_creates_class_alias_for_subclass(): void {
		$this->mock_avif_transparency_support( false );

		// Ensure WordPress's base image editor classes are loaded.
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';

		if ( ! class_exists( 'WP_Image_Editor_Imagick' ) ) {
			$this->markTestSkipped( 'WP_Image_Editor_Imagick class is not available.' );
		}

		require TESTS_PLUGIN_DIR . '/tests/data/class-custom-image-editor-imagick.php';

		if ( ! class_exists( 'Custom_Image_Editor_Imagick' ) ) {
			$this->markTestSkipped( 'Custom_Image_Editor_Imagick class could not be loaded.' );
		}

		$editors = webp_uploads_set_image_editors( array( 'Custom_Image_Editor_Imagick' ) );

		// Should prepend custom editor.
		$this->assertContains( 'WebP_Uploads_Image_Editor_Imagick', $editors );
		$this->assertSame( 'WebP_Uploads_Image_Editor_Imagick', $editors[0] );
	}

	/**
	 * Tests webp_uploads_set_image_editors with class that doesn't exist.
	 *
	 * @covers ::webp_uploads_set_image_editors
	 */
	public function test_webp_uploads_set_image_editors_with_nonexistent_class(): void {
		$original_editors = array( 'NonExistent_Editor' );
		$editors          = webp_uploads_set_image_editors( $original_editors );

		$this->assertSame( $original_editors, $editors );
	}

	/**
	 * Data provider for testing various image files for transparency detection.
	 *
	 * @return array<string, array{string, bool}> Test data.
	 */
	public function data_provider_image_transparency_detection(): array {
		return array(
			'transparent PNG'         => array( 'dice.png', true ),
			'transparent palette PNG' => array( 'dice-palette.png', true ),
			'non-transparent JPEG'    => array( 'car.jpeg', false ),
			'non-transparent WebP'    => array( 'balloons.webp', false ),
		);
	}

	/**
	 * Tests has_transparency with various image types using data provider.
	 *
	 * @dataProvider data_provider_image_transparency_detection
	 * @covers WebP_Uploads_Image_Editor_Imagick::has_transparency
	 *
	 * @param string $image_filename The image filename.
	 * @param bool   $expected_transparency Expected transparency result.
	 */
	public function test_has_transparency_with_various_images( string $image_filename, bool $expected_transparency ): void {
		$this->setup_custom_image_editor( false );

		$editor = wp_get_image_editor( TESTS_PLUGIN_DIR . '/tests/data/images/' . $image_filename );

		$this->assertNotWPError( $editor, 'Failed to create image editor.' );
		// @phpstan-ignore-next-line Class extends runtime alias WebP_Uploads_Image_Editor_Imagick_Base.
		$this->assertInstanceOf( WebP_Uploads_Image_Editor_Imagick::class, $editor, 'Editor is not the custom image editor class.' );

		$has_transparency = $editor->has_transparency();

		$this->assertNotInstanceOf( WP_Error::class, $has_transparency );
		$this->assertSame( $expected_transparency, $has_transparency );
	}

	/**
	 * Data provider for testing webp_uploads_set_image_editors with different conditions.
	 *
	 * @return array<string, array{string, array<string>, bool}> Test data.
	 */
	public function data_provider_set_image_editors_conditions(): array {
		return array(
			'empty editors array' => array( 'avif', array(), false ),
			'WebP output format'  => array( 'webp', array( 'WP_Image_Editor_Imagick' ), false ),
			'GD editor first'     => array( 'avif', array( 'WP_Image_Editor_GD', 'WP_Image_Editor_Imagick' ), false ),
		);
	}

	/**
	 * Tests webp_uploads_set_image_editors returns original array for various conditions.
	 *
	 * @dataProvider data_provider_set_image_editors_conditions
	 * @covers ::webp_uploads_set_image_editors
	 *
	 * @param string   $output_format Output format setting.
	 * @param string[] $editors Array of editor class names.
	 * @param bool     $should_modify Whether the array should be modified.
	 */
	public function test_webp_uploads_set_image_editors_with_various_conditions( string $output_format, array $editors, bool $should_modify ): void {
		$this->mock_avif_transparency_support( false );

		$this->set_image_output_type( $output_format );

		$result = webp_uploads_set_image_editors( $editors );

		if ( ! $should_modify ) {
			$this->assertSame( $editors, $result );
		} else {
			$this->assertNotSame( $editors, $result );
			$this->assertContains( 'WebP_Uploads_Image_Editor_Imagick', $result );
		}
	}

	/**
	 * Data provider for testing site health responses.
	 *
	 * @return array<string, array{string, string}> Test data.
	 */
	public function data_provider_site_health_structure(): array {
		return array(
			'label key'       => array( 'label', 'string' ),
			'status key'      => array( 'status', 'string' ),
			'badge key'       => array( 'badge', 'array' ),
			'description key' => array( 'description', 'string' ),
			'actions key'     => array( 'actions', 'string' ),
			'test key'        => array( 'test', 'string' ),
		);
	}

	/**
	 * Tests site health function structure using data provider.
	 *
	 * @dataProvider data_provider_site_health_structure
	 * @covers ::webp_uploads_imagick_avif_transparency_supported_test
	 *
	 * @param string $key The array key to check.
	 * @param string $type The expected type.
	 */
	public function test_site_health_structure_has_required_keys( string $key, string $type ): void {
		$result = webp_uploads_imagick_avif_transparency_supported_test();

		$this->assertArrayHasKey( $key, $result );

		if ( 'string' === $type ) {
			$this->assertIsString( $result[ $key ] );
		} elseif ( 'array' === $type ) {
			$this->assertIsArray( $result[ $key ] );
		}
	}

	/**
	 * Mocks AVIF transparency support to force a specific scenario.
	 *
	 * @param bool $supported Whether to mock AVIF transparency as supported.
	 */
	private function mock_avif_transparency_support( bool $supported ): void {
		add_filter(
			'webp_uploads_imagick_avif_transparency_supported',
			static function () use ( $supported ) {
				return $supported;
			},
			1
		);
	}

	/**
	 * Ensures the WebP_Uploads_Image_Editor_Imagick class is loaded for testing.
	 */
	private function ensure_custom_editor_loaded(): void {
		wp_image_editor_supports();
	}

	/**
	 * Sets up custom image editor for tests that need it.
	 *
	 * @param bool $transparency_supported Whether AVIF transparency is supported.
	 */
	private function setup_custom_image_editor( bool $transparency_supported = false ): void {
		$this->mock_avif_transparency_support( $transparency_supported );
		$this->ensure_custom_editor_loaded();
		add_filter( 'wp_image_editors', 'webp_uploads_set_image_editors' );

		if ( ! class_exists( 'WebP_Uploads_Image_Editor_Imagick' ) ) {
			$this->markTestSkipped( 'WebP_Uploads_Image_Editor_Imagick class is not available.' );
		}
	}
}
