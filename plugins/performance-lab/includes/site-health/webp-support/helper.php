<?php
/**
 * Helper functions used for WebP Support.
 *
 * @package performance-lab
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Callback for webp_enabled test.
 *
 * @since 1.0.0
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} Result.
 */
function webp_uploads_check_webp_supported_test(): array {
	$result = array(
		'label'       => __( 'Your site supports WebP', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			__( 'The WebP image format produces images that are usually smaller in size than JPEG images, which can reduce page load time and consume less bandwidth.', 'performance-lab' )
		),
		'actions'     => '',
		'test'        => 'is_webp_uploads_enabled',
	);

	$webp_supported = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );

	if ( ! $webp_supported ) {
		$result['status']  = 'recommended';
		$result['label']   = __( 'Your site does not support WebP', 'performance-lab' );
		$result['actions'] = sprintf(
			'<p>%s</p>',
			/* translators: Accessibility text. */
			__( 'WebP images are not currently supported by your web servers image processing libraries (Imagick or LibGD). This functionality is required to serve WebP images and improve website speed. Fortunately, these libraries have supported WebP for quite a while, so support is widely available. If your server does not support WebP, you will see an error message when you try to upload a WebP image. To check your supported file formats, go to Tools > Site Health > Info > Media Handling > Supported File Formats. For more information, visit <a href="https://learn.wordpress.org/lesson-plan/webp-images-in-wordpress/" target="_blank">WebP images in WordPress</a>.', 'performance-lab' )
		);
	}

	return $result;
}
