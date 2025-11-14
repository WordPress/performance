<?php
/**
 * Helper functions used for checking Imagick AVIF transparency support.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Callback for webp_enabled test.
 *
 * @since n.e.x.t
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} Result.
 */
function perflab_imagick_avif_transparency_supported_test(): array {
	$result = array(
		'label'       => __( 'Your site supports AVIF image format transparency with ImageMagick', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			__( 'Older versions of ImageMagick do not support transparency in AVIF images, which can result in loss of transparency when uploading AVIF files.', 'performance-lab' )
		),
		'actions'     => sprintf(
			'<p><strong>%s</strong></p>',
			__( 'Your ImageMagick installation supports AVIF transparency.', 'performance-lab' )
		),
		'test'        => 'is_imagick_avif_transparency_supported_enabled',
	);

	if ( ! perflab_imagick_avif_transparency_supported() ) {
		$result['status']  = 'recommended';
		$result['label']   = __( 'Your site does not support AVIF transparency', 'performance-lab' );
		$result['actions'] = sprintf(
			'<p>%s</p>',
			__( 'Update ImageMagick to the latest version by contacting your hosting provider.', 'performance-lab' )
		);
	}

	return $result;
}

/**
 * Checks if Imagick has AVIF transparency support.
 *
 * @since n.e.x.t
 *
 * @return bool True if Imagick has AVIF transparency support, false otherwise.
 */
function perflab_imagick_avif_transparency_supported(): bool {
	if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
		$imagick_version = Imagick::getVersion();
		if ( (bool) preg_match( '/\d+(?:\.\d+)+(?:-\d+)?/', $imagick_version['versionString'], $matches ) ) {
			$imagick_version = $matches[0];
		} else {
			$imagick_version = $imagick_version['versionString'];
		}
		return version_compare( $imagick_version, '7.0.25', '>=' );
	}

	return false;
}
