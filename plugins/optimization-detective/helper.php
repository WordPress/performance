<?php
/**
 * Helper functions for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Constructs XPath from indexed breadcrumbs.
 *
 * @since n.e.x.t
 *
 * @param array<int, array{ string, non-negative-int }> $breadcrumbs Indexed breadcrumbs.
 * @return string XPath.
 */
function od_construct_xpath( array $breadcrumbs ): string {
	$xpath = '';
	foreach ( $breadcrumbs as $breadcrumb ) {
		$xpath .= sprintf( '/*[%d][self::%s]', $breadcrumb[1] + 1, $breadcrumb[0] );
	}
	return $xpath;
}

/**
 * Displays the HTML generator meta tag for the Optimization Detective plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.0
 */
function od_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="optimization-detective ' . esc_attr( OPTIMIZATION_DETECTIVE_VERSION ) . '">' . "\n";
}
