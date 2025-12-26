<?php
/**
 * Helper functions used for checking Imagick AVIF transparency support.
 *
 * @package webp-uploads
 * @since n.e.x.t
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Callback for Imagick AVIF transparency support test.
 *
 * @since n.e.x.t
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} Result.
 */
function webp_uploads_imagick_avif_transparency_supported_test(): array {
	$result = array(
		'label'       => __( 'Your site supports AVIF image format transparency with ImageMagick', 'webp-uploads' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'webp-uploads' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			__( 'Older versions of ImageMagick do not support transparency in AVIF images, which can result in loss of transparency when uploading AVIF files.', 'webp-uploads' )
		),
		'actions'     => sprintf(
			'<p><strong>%s</strong></p>',
			__( 'Your ImageMagick installation supports AVIF transparency.', 'webp-uploads' )
		),
		'test'        => 'is_imagick_avif_transparency_supported_enabled',
	);

	if ( ! webp_uploads_imagick_avif_transparency_supported() ) {
		$result['status']  = 'recommended';
		$result['label']   = __( 'Your site does not support AVIF transparency', 'webp-uploads' );
		$result['actions'] = sprintf(
			'<p>%s</p>',
			__( 'Update ImageMagick to the latest version by contacting your hosting provider.', 'webp-uploads' )
		);
	}

	return $result;
}
