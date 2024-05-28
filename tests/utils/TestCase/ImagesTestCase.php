<?php

namespace PerformanceLab\Tests\TestCase;

use PerformanceLab\Tests\Constraint\ImageHasSizeSource;
use PerformanceLab\Tests\Constraint\ImageHasSource;
use WP_UnitTestCase;

/**
 * A test case for image attachments.
 *
 * @method void assertImageHasSource( int $attachment_id, string $mime_type, string $message = '' ) Asserts that the image has the appropriate source.
 * @method void assertImageHasSizeSource( int $attachment_id, string $size_name, string $mime_type, string $message = '' ) Asserts that the image has the appropriate source for the subsize.
 * @method void assertImageNotHasSource( int $attachment_id, string $mime_type, string $message = '' ) Asserts that the image doesn't have the appropriate source.
 * @method void assertImageNotHasSizeSource( int $attachment_id, string $size_name, string $mime_type, string $message = '' ) Asserts that the image doesn't have the appropriate source for the subsize.
 * @method void assertFileNameIsEdited( string $filename, string $message = '' ) Asserts that the provided file name was edited by WordPress contains an e{WITH_13_DIGITS} on the filename.
 * @method void assertFileNameIsNotEdited( string $filename, string $message = '' ) Asserts that the provided file name was edited by WordPress contains an e{WITH_13_DIGITS} on the filename.
 * @method void assertSizeNameIsHashed( string $size_name, string $hashed_size_name, string $message = '' ) Asserts that the provided size name is an edited name that contains a hash with digits.
 */
abstract class ImagesTestCase extends WP_UnitTestCase {

	/**
	 * Asserts that an image has a source with the specific mime type.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $mime_type     The mime type of the source.
	 * @param string $message       An optional message to show on failure.
	 */
	public static function assertImageHasSource( int $attachment_id, string $mime_type, string $message = '' ): void {
		$constraint = new ImageHasSource( $mime_type );
		self::assertThat( $attachment_id, $constraint, $message );
	}

	/**
	 * Asserts that an image doesn't have a source with the specific mime type.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $mime_type     The mime type of the source.
	 * @param string $message       An optional message to show on failure.
	 */
	public static function assertImageNotHasSource( int $attachment_id, string $mime_type, string $message = '' ): void {
		$constraint = new ImageHasSource( $mime_type );
		$constraint->isNot();
		self::assertThat( $attachment_id, $constraint, $message );
	}

	/**
	 * Asserts that an image has a source with the specific mime type for a subsize.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $size_name     The subsize name.
	 * @param string $mime_type     The mime type of the source.
	 * @param string $message       An optional message to show on failure.
	 */
	public static function assertImageHasSizeSource( int $attachment_id, string $size_name, string $mime_type, string $message = '' ): void {
		$constraint = new ImageHasSizeSource( $mime_type, $size_name );
		self::assertThat( $attachment_id, $constraint, $message );
	}

	/**
	 * Asserts that an image doesn't have a source with the specific mime type for a subsize.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $size_name     The subsize name.
	 * @param string $mime_type     The mime type of the source.
	 * @param string $message       An optional message to show on failure.
	 */
	public static function assertImageNotHasSizeSource( int $attachment_id, string $size_name, string $mime_type, string $message = '' ): void {
		$constraint = new ImageHasSizeSource( $mime_type, $size_name );
		$constraint->isNot();
		self::assertThat( $attachment_id, $constraint, $message );
	}

	/**
	 * Asserts that the provided file name was edited by WordPress contains an e{WITH_13_DIGITS} on the filename.
	 *
	 * @param string $filename The name of the filename to be asserted.
	 * @param string $message  The Error message used to display when the assertion fails.
	 */
	public static function assertFileNameIsEdited( string $filename, string $message = '' ): void {
		self::assertMatchesRegularExpression( '/e\d{13}/', $filename, $message );
	}

	/**
	 * Asserts that the provided file name was edited by WordPress contains an e{WITH_13_DIGITS} on the filename.
	 *
	 * @param string $filename The name of the filename to be asserted.
	 * @param string $message  The Error message used to display when the assertion fails.
	 */
	public static function assertFileNameIsNotEdited( string $filename, string $message = '' ): void {
		self::assertDoesNotMatchRegularExpression( '/e\d{13}/', $filename, $message );
	}

	/**
	 * Asserts that the provided size name is an edited name that contains a hash with digits.
	 *
	 * @param string $size_name        The size name we are looking for.
	 * @param string $hashed_size_name The current size name we are comparing against.
	 * @param string $message          The Error message used to display when the assertion fails.
	 */
	public static function assertSizeNameIsHashed( string $size_name, string $hashed_size_name, string $message = '' ): void {
		self::assertMatchesRegularExpression( "/{$size_name}-\d{13}/", $hashed_size_name, $message );
	}

	/**
	 * Adds filter so that for a JPEG upload both JPEG and WebP versions are generated.
	 */
	public function opt_in_to_jpeg_and_webp(): void {
		add_filter(
			'webp_uploads_upload_image_mime_transforms',
			static function ( $transforms ) {
				$transforms['image/jpeg'] = array( 'image/jpeg', 'image/webp' );
				$transforms['image/webp'] = array( 'image/webp', 'image/jpeg' );
				return $transforms;
			}
		);
	}

	/**
	 * Opt into picture element output.
	 */
	public function opt_in_to_picture_element(): void {
		add_filter( 'wp_content_img_tag', 'webp_uploads_wrap_image_in_picture', 10, 3 );
	}
}
