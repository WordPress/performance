<?php

namespace PerformanceLab\Tests\TestCase;

use PerformanceLab\Tests\Constraint\ImageHasSizeSource;
use PerformanceLab\Tests\Constraint\ImageHasSource;
use WP_UnitTestCase;

/**
 * A test case for image attachments.
 *
 * @method void assertImageHasSource( $attachment_id, $mime_type, $message ) Asserts that the image has the appropriate source.
 * @method void assertImageHasSizeSource( $attachment_id, $size, $mime_type, $message ) Asserts that the image has the appropriate source for the subsize.
 * @method void assertImageNotHasSource( $attachment_id, $mime_type, $message ) Asserts that the image doesn't have the appropriate source.
 * @method void assertImageNotHasSizeSource( $attachment_id, $size, $mime_type, $message ) Asserts that the image doesn't have the appropriate source for the subsize.
 * @method void assertFileNameIsEdited( string $filename ) Asserts that the provided file name was edited by WordPress contains an e{WITH_13_DIGITS} on the filename.
 * @method void assertSizeNameIsHashed( string $size_name, string $hashed_size_name ) Asserts that the provided size name is an edited name that contains a hash with digits.
 */
abstract class ImagesTestCase extends WP_UnitTestCase {

	/**
	 * Asserts that an image has a source with the specific mime type.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $mime_type The mime type of the source.
	 * @param string $message An optional message to show on failure.
	 */
	public static function assertImageHasSource( $attachment_id, $mime_type, $message = '' ) {
		$constraint = new ImageHasSource( $mime_type );
		self::assertThat( $attachment_id, $constraint, $message );
	}

	/**
	 * Asserts that an image doesn't have a source with the specific mime type.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $mime_type The mime type of the source.
	 * @param string $message An optional message to show on failure.
	 */
	public static function assertImageNotHasSource( $attachment_id, $mime_type, $message = '' ) {
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
	 * @param string $size The subsize name.
	 * @param string $mime_type The mime type of the source.
	 * @param string $message An optional message to show on failure.
	 */
	public static function assertImageHasSizeSource( $attachment_id, $size, $mime_type, $message = '' ) {
		$constraint = new ImageHasSizeSource( $mime_type, $size );
		self::assertThat( $attachment_id, $constraint, $message );
	}

	/**
	 * Asserts that an image doesn't have a source with the specific mime type for a subsize.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $size The subsize name.
	 * @param string $mime_type The mime type of the source.
	 * @param string $message An optional message to show on failure.
	 */
	public static function assertImageNotHasSizeSource( $attachment_id, $size, $mime_type, $message = '' ) {
		$constraint = new ImageHasSizeSource( $mime_type, $size );
		$constraint->isNot();
		self::assertThat( $attachment_id, $constraint, $message );
	}

	/**
	 * Asserts that the provided file name was edited by WordPress contains an e{WITH_13_DIGITS} on the filename.
	 *
	 * @param string $filename The name of the filename to be asserted.
	 * @return void
	 */
	public static function assertFileNameIsEdited( $filename ) {
		self::assertRegExp( '/e\d{13}/', $filename );
	}

	/**
	 * Asserts that the provided size name is an edited name that contains a hash with digits.
	 *
	 * @param string $size_name        The size name we are looking for.
	 * @param string $hashed_size_name The current size name we are comparing against.
	 * @return void
	 */
	public static function assertSizeNameIsHashed( $size_name, $hashed_size_name ) {
		self::assertRegExp( "/{$size_name}-\d{13}/", $hashed_size_name );
	}
}
