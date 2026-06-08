<?php
/**
 * Tests for Image Placeholders plugin.
 *
 * @package dominant-color-images
 * @noinspection PhpComposerExtensionStubsInspection
 */

use Dominant_Color_Images\Tests\TestCase;

/**
 * @coversDefaultClass Dominant_Color_Image_Editor_GD
 */
class Test_Dominant_Color_Image_Editor_GD extends TestCase {

	/**
	 * Makes sure that only the GD editor is used.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! extension_loaded( 'gd' ) || ! function_exists( 'gd_info' ) ) {
			$this->markTestSkipped( 'The GD PHP extension is not loaded.' );
		}

		// ensure the GD editor is registered. Doesnt seem to be by the time this runs.
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';
		require_once __DIR__ . '/../class-dominant-color-image-editor-gd.php';

		add_filter(
			'wp_image_editors',
			static function ( array $editors ): array {
				return array_filter(
					$editors,
					static function ( $editor ): bool {
						return WP_Image_Editor_GD::class === $editor;
					}
				);
			}
		);
	}

	/**
	 * @covers ::get_dominant_color
	 */
	public function test_invalid_image_type(): void {
		$editor = new Dominant_Color_Image_Editor_GD( '/invalid/type' );
		$result = $editor->get_dominant_color();
		$this->assertWPError( $result );
		$this->assertSame( 'image_editor_dominant_color_error_no_image', $result->get_error_code() );
	}

	/**
	 * @covers ::get_dominant_color
	 */
	public function test_get_dominant_color_no_image(): void {
		$editor = new Dominant_Color_Image_Editor_GD( null );
		$result = $editor->get_dominant_color();

		$this->assertWPError( $result );
		$this->assertSame( 'image_editor_dominant_color_error_no_image', $result->get_error_code() );
	}

	/**
	 * @covers ::has_transparency
	 */
	public function test_has_transparency_no_image(): void {
		$editor = new Dominant_Color_Image_Editor_GD( null );
		$result = $editor->has_transparency();

		$this->assertWPError( $result );
		$this->assertSame( 'image_editor_has_transparency_error_no_image', $result->get_error_code() );
	}

	/**
	 * @covers ::get_dominant_color
	 */
	public function test_get_dominant_color_success(): void {
		$im = imagecreatetruecolor( 1, 1 );
		$red = imagecolorallocate( $im, 255, 0, 0 );
		imagefill( $im, 0, 0, $red );

		$editor = new Dominant_Color_Image_Editor_GD( null );
		$reflection = new ReflectionClass( $editor );
		$property = $reflection->getProperty( 'image' );
		$property->setAccessible( true );
		$property->setValue( $editor, $im );

		$result = $editor->get_dominant_color();

		$this->assertIsArray( $result );
		$this->assertSame( array( 'r' => 255, 'g' => 0, 'b' => 0 ), $result );
	}

	/**
	 * @covers ::has_transparency
	 */
	public function test_has_no_transparency(): void {
		$im = imagecreatetruecolor( 1, 1 );
		$red = imagecolorallocate( $im, 255, 0, 0 );
		imagefill( $im, 0, 0, $red );

		$editor = new Dominant_Color_Image_Editor_GD( null );
		$reflection = new ReflectionClass( $editor );
		$property = $reflection->getProperty( 'image' );
		$property->setAccessible( true );
		$property->setValue( $editor, $im );

		$result = $editor->has_transparency();

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::has_transparency
	 */
	public function test_has_transparency_with_transparency(): void {
		$im = imagecreatetruecolor( 1, 1 );
		$alpha_color = imagecolorallocatealpha( $im, 255, 0, 0, 64 );
		imagefill( $im, 0, 0, $alpha_color );

		$editor = new Dominant_Color_Image_Editor_GD( null );
		$reflection = new ReflectionClass( $editor );
		$property = $reflection->getProperty( 'image' );
		$property->setAccessible( true );
		$property->setValue( $editor, $im );

		$result = $editor->has_transparency();

		$this->assertTrue( $result );
	}
}
