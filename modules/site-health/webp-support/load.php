<?php
/**
 * Module Name: Web Support
 * Description: Adds a WebP support check in Site Health checks.
 * Experimental: No
 *
 * @package performance-lab
 * @since 1.0.0
 */

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
		'label' => esc_html__( 'WebP Support', 'performance-lab' ),
		'test'  => 'webp_uploads_check_webp_supported_test',
	);
	return $tests;
}
add_filter( 'site_status_tests', 'webp_uploads_add_is_webp_supported_test' );

/**
 * Callback for webp_enabled test.
 *
 * @since 1.0.0
 *
 * @return array
 */
function webp_uploads_check_webp_supported_test() {
	$result = array(
		'label'       => __( 'Your site supports WebP', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			__( 'WebP image format is used by WordPress to improve the performance of your site by generating smaller images than it usually could with the JPEG format. This means your pages will load faster and consume less bandwidth.', 'performance-lab' )
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
			__( 'Please contact your host and ask them to add WebP support.', 'performance-lab' )
		);
	}

	return $result;
}
