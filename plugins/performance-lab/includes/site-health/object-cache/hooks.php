<?php
/**
 * Hook callbacks used for Site Health Info.
 *
 * @package performance-lab
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds Object Cache module to Site Health Info.
 *
 * @param  array  $info  Site Health Info.
 *
 * @return array Amended Info.
 * @since 3.3.0
 *
 */
function object_cache_supported_info( array $info ): array {
	$info['object_cache'] = array(
		'label'       => __( 'Object Caching', 'performance-lab' ),
		'description' => __( 'Shows which features object cache supports and if object caching is in use.',
			'performance-lab' ),
		'fields'      => object_cache_supported_fields(),
	);

	return $info;
}

add_filter( 'debug_information', 'object_cache_supported_info', 10, 1 );
