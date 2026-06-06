<?php
/**
 * Tests for Image Placeholders plugin.
 *
 * @since 1.2.0
 *
 * @package dominant-color-images
 * @noinspection PhpComposerExtensionStubsInspection
 * @noinspection PhpUnhandledExceptionInspection
 */

use Dominant_Color_Images\Tests\TestCase;

/**
 * @coversDefaultClass Dominant_Color_Image_Editor_Imagick
 */
class Test_Dominant_Color_Image_Editor_Imagick extends TestCase {

	/**
	 * Makes sure that only the Imagick editor is used.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick', false ) ) {
			$this->markTestSkipped( 'The Imagick PHP extension is not loaded.' );
		}

		$required_formats = array( 'JPEG', 'PNG', 'GIF', 'WEBP' );
		foreach ( $required_formats as $required_format ) {
			if ( ! in_array( $required_format, Imagick::queryFormats( $required_format ), true ) ) {
				return;
			}
		}

		add_filter(
			'wp_image_editors',
			static function ( array $editors ): array {
				return array_filter(
					$editors,
					static function ( $editor ): bool {
						return WP_Image_Editor_Imagick::class === $editor;
					}
				);
			}
		);
	}

	/**
	 * @covers ::get_dominant_color
	 */
	public function test_failure_handling(): void {
		$editor = new Dominant_Color_Image_Editor_Imagick( '/image.jpg' );
		$result = $editor->get_dominant_color();
		$this->assertWPError( $result );
		$this->assertEquals( 'image_editor_dominant_color_error_no_image', $result->get_error_code() );
	}

	/**
	 * @covers ::get_dominant_color
	 */
	public function test_get_dominant_color_no_image(): void {
		$editor = new Dominant_Color_Image_Editor_Imagick( null );
		$result = $editor->get_dominant_color();

		$this->assertWPError( $result );
		$this->assertEquals( 'image_editor_dominant_color_error_no_image', $result->get_error_code() );
	}

	/**
	 * @covers ::get_dominant_color
	 */
	public function test_get_dominant_color_exception(): void {
		// Creating mock that will throw an exception.
		$mock = $this->getMockBuilder( Dominant_Color_Image_Editor_Imagick::class )
					->onlyMethods( array( '__construct' ) )
					->disableOriginalConstructor()
					->getMock();

		$reflection = new ReflectionClass( $mock );
		$property   = $reflection->getProperty( 'image' );
		$property->setAccessible( true );
		$property->setValue( $mock, new Imagick() );

		$result = $mock->get_dominant_color();

		$this->assertWPError( $result );
		$this->assertEquals( 'image_editor_dominant_color_error', $result->get_error_code() );
	}

	/**
	 * @covers ::has_transparency
	 */
	public function test_has_transparency_no_image(): void {
		$editor = new Dominant_Color_Image_Editor_Imagick( null );
		$result = $editor->has_transparency();

		$this->assertWPError( $result );
		$this->assertEquals( 'image_editor_has_transparency_error_no_image', $result->get_error_code() );
	}

	/**
	 * @covers ::has_transparency
	 */
	public function test_has_no_transparency(): void {
		$imagick = new Imagick();
		$imagick->newImage( 1, 1, new ImagickPixel( 'red' ) );

		$editor     = new Dominant_Color_Image_Editor_Imagick( null );
		$reflection = new ReflectionClass( $editor );
		$property   = $reflection->getProperty( 'image' );
		$property->setAccessible( true );
		$property->setValue( $editor, $imagick );

		$result = $editor->has_transparency();

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::has_transparency
	 */
	public function test_has_transparency_exception(): void {
		// Creating mock that will throw an exception.
		$mock = $this->getMockBuilder( Dominant_Color_Image_Editor_Imagick::class )
					->onlyMethods( array( '__construct' ) )
					->disableOriginalConstructor()
					->getMock();

		$reflection = new ReflectionClass( $mock );
		$property   = $reflection->getProperty( 'image' );
		$property->setAccessible( true );
		$property->setValue( $mock, new Imagick() );

		$result = $mock->has_transparency();

		$this->assertWPError( $result );
		$this->assertEquals( 'image_editor_has_transparency_error', $result->get_error_code() );
	}

	/**
	 * @covers ::get_dominant_color
	 */
	public function test_get_dominant_color_success(): void {
		// Create a red test image.
		$imagick = new Imagick();
		$imagick->newImage( 1, 1, new ImagickPixel( '#FF0000' ) );

		$editor     = new Dominant_Color_Image_Editor_Imagick( null );
		$reflection = new ReflectionClass( $editor );
		$property   = $reflection->getProperty( 'image' );
		$property->setAccessible( true );
		$property->setValue( $editor, $imagick );

		$result = $editor->get_dominant_color();

		$this->assertEquals( 'ff0000', $result );
	}

	/**
	 * @covers ::has_transparency
	 */
	public function test_has_transparency_with_transparency(): void {
		// Create an image with transparency.
		$imagick = new Imagick();
		$imagick->newImage( 1, 1, new ImagickPixel( 'rgba(255,0,0,0.5)' ) );

		$editor     = new Dominant_Color_Image_Editor_Imagick( null );
		$reflection = new ReflectionClass( $editor );
		$property   = $reflection->getProperty( 'image' );
		$property->setAccessible( true );
		$property->setValue( $editor, $imagick );

		$result = $editor->has_transparency();

		$this->assertTrue( $result );
	}
}
