<?php
/**
 * Site Health test for AVIF support.
 *
 * @package performance-lab
 */

namespace Performance_Lab\Modern_Image_Formats\Site_Health;

/**
 * Check if AVIF support is available.
 *
 * @return array Result of the AVIF support test.
 */
function test_avif_support(): array {
	if ( ! function_exists( 'imageavif' ) ) {
		return array(
			'label'       => __( 'AVIF support', 'performance-lab' ),
			'status'      => 'recommended',
			'description' => __( 'Your server does not currently support AVIF image format.', 'performance-lab' ),
		);
	}

	return array(
		'label'       => __( 'AVIF support', 'performance-lab' ),
		'status'      => 'good',
		'description' => __( 'Your server supports AVIF image format.', 'performance-lab' ),
	);
}
