<?php
/**
 * Site Health test for WebP support.
 *
 * @package performance-lab
 */

namespace Performance_Lab\Modern_Image_Formats\Site_Health;

/**
 * Check if WebP support is available.
 *
 * @return array Result of the WebP support test.
 */
function test_webp_support(): array {
	if ( ! function_exists( 'imagewebp' ) ) {
		return array(
			'label'       => __( 'WebP support', 'performance-lab' ),
			'status'      => 'recommended',
			'description' => __( 'Your server does not currently support WebP image format.', 'performance-lab' ),
		);
	}

	return array(
		'label'       => __( 'WebP support', 'performance-lab' ),
		'status'      => 'good',
		'description' => __( 'Your server supports WebP image format.', 'performance-lab' ),
	);
}
