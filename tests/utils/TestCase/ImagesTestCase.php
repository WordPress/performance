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
 */
abstract class ImagesTestCase extends WP_UnitTestCase {

	/**
	 * Asserts that an image has a source with the specific mime type.
	 *
	 * @since n.e.x.t
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
	 * @since n.e.x.t
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
	 * @since n.e.x.t
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
	 * @since n.e.x.t
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

}
