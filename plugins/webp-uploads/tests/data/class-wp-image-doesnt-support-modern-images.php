<?php
/**
 * A WP_Image_Editor mock that doesn't support Modern Images.
 *
 * @package webp-uploads
 * @since 1.0.0
 */

if ( ! class_exists( 'WP_Image_Editor_Imagick' ) ) {
	require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
	require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';
}

// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint -- Because the subclass needs to be compatible with the base class.

/**
 * Class WP_Image_Doesnt_Support_Modern_Images mocks a WP_Image_Editor that doesn't support Modern Images.
 *
 * @since 1.0.0
 */
class WP_Image_Doesnt_Support_Modern_Images extends WP_Image_Editor_Imagick {
	/**
	 * Checks to see if editor supports the mime-type specified.
	 *
	 * @param string $mime_type The mime type to check.
	 * @return bool Supports.
	 */
	public static function supports_mime_type( $mime_type ): bool {
		return (
			'image/webp' !== $mime_type &&
			'image/avif' !== $mime_type
		);
	}
}
