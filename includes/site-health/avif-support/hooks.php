<?php
/**
 * Hook callbacks used for AVIF Support.
 *
 * @package performance-lab
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds tests to site health.
 *
 * @since 1.0.0
 *
 * @param array $tests Site Health Tests.
 * @return array
 */
function avif_uploads_add_is_avif_supported_test( $tests ) {
	$tests['direct']['avif_supported'] = array(
		'label' => __( 'AVIF Support', 'performance-lab' ),
		'test'  => 'avif_uploads_check_avif_supported_test',
	);
	return $tests;
}
add_filter( 'site_status_tests', 'avif_uploads_add_is_avif_supported_test' );
