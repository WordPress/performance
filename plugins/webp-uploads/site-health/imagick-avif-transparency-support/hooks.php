<?php
/**
 * Hook callbacks used for checking Imagick AVIF transparency support.
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
 * Adds tests to site health.
 *
 * @since n.e.x.t
 *
 * @param array{direct: array<string, array{label: string, test: string}>} $tests Site Health Tests.
 * @return array{direct: array<string, array{label: string, test: string}>} Amended tests.
 */
function webp_uploads_add_imagick_avif_transparency_supported_test( array $tests ): array {
	$tests['direct']['imagick_avif_transparency_supported'] = array(
		'label' => __( 'Imagick AVIF Transparency Support', 'webp-uploads' ),
		'test'  => 'webp_uploads_imagick_avif_transparency_supported_test',
	);
	return $tests;
}
add_filter( 'site_status_tests', 'webp_uploads_add_imagick_avif_transparency_supported_test' ); // @codeCoverageIgnore
