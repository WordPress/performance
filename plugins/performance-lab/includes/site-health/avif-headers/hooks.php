<?php
/**
 * Hook callbacks used for AVIF Headers.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds tests to site health.
 *
 * @since n.e.x.t
 *
 * @param array{direct: array<string, array{label: string, test: string}>} $tests Site Health Tests.
 * @return array{direct: array<string, array{label: string, test: string}>} Amended tests.
 */
function avif_headers_add_is_avif_headers_enabled_test( array $tests ): array {
	$tests['direct']['avif_headers'] = array(
		'label' => __( 'AVIF Headers', 'performance-lab' ),
		'test'  => 'avif_headers_check_avif_headers_test',
	);
	return $tests;
}
add_filter( 'site_status_tests', 'avif_headers_add_is_avif_headers_enabled_test' );
