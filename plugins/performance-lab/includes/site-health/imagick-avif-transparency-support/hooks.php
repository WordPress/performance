<?php
/**
 * Hook callbacks used for checking Imagick AVIF transparency support.
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
 * Adds tests to site health.
 *
 * @since n.e.x.t
 *
 * @param array{direct: array<string, array{label: string, test: string}>} $tests Site Health Tests.
 * @return array{direct: array<string, array{label: string, test: string}>} Amended tests.
 */
function perflab_add_imagick_avif_transparency_supported_test( array $tests ): array {
	$tests['direct']['imagick_avif_transparency_supported'] = array(
		'label' => __( 'Imagick AVIF Transparency Support', 'performance-lab' ),
		'test'  => 'perflab_imagick_avif_transparency_supported_test',
	);
	return $tests;
}
add_filter( 'site_status_tests', 'perflab_add_imagick_avif_transparency_supported_test' );
