<?php
/**
 * Hook callbacks used for Site Health Info.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds Object Cache module to Site Health Info.
 *
 * @param array{object_cache: array{label: string,description: string,fields: array<string, array{label: string,value: string}>}} $info Site Health Info.
 *
 * @return array{object_cache: array{label: string,description: string,fields: array<string, array{label: string,value: string}>}} Amended Info.
 * @since n.e.x.t
 */
function performance_lab_object_cache_supported_info( array $info ): array {
	$info['object_cache'] = array(
		'label'       => __( 'Object Caching', 'performance-lab' ),
		'description' => __( 'Shows which features object cache supports and if object caching is in use.', 'performance-lab' ),
		'fields'      => performance_lab_object_cache_supported_fields(),
	);

	return $info;
}

add_filter( 'debug_information', 'performance_lab_object_cache_supported_info', 10, 1 );
