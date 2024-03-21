<?php
/**
 * Hook callbacks used for WebP Support.
 *
 * @package performance-lab
 * @since 2.1.0
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
function webp_uploads_add_is_webp_supported_test( $tests ) {
	$tests['direct']['webp_supported'] = array(
		'label' => __( 'WebP Support', 'performance-lab' ),
		'test'  => 'webp_uploads_check_webp_supported_test',
	);
	return $tests;
}
add_filter( 'site_status_tests', 'webp_uploads_add_is_webp_supported_test' );
