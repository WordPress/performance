<?php
/**
 * Web Worker Offloading integration with Rank Math SEO.
 *
 * @since 0.2.0
 * @package web-worker-offloading
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Configures WWO for Rank Math SEO and Google Analytics.
 *
 * @since 0.2.0
 * @access private
 * @link https://partytown.builder.io/google-tag-manager#forward-events
 *
 * @param array<string, mixed>|mixed $configuration Configuration.
 * @return array<string, mixed> Configuration.
 */
function plwwo_rank_math_configure( $configuration ): array {
	$configuration = (array) $configuration;

	$configuration['globalFns'][] = 'gtag'; // Because gtag() is defined in one script and called in another.

	// Expose on the main tread. See <https://partytown.builder.io/forwarding-event>.
	$configuration['forward'][] = 'dataLayer.push';
	$configuration['forward'][] = 'gtag';
	return $configuration;
}
add_filter( 'plwwo_configuration', 'plwwo_rank_math_configure' );

/*
 * Note: The following integration is not targeting the \RankMath\Analytics\GTag::enqueue_gtag_js() code which is only
 * used for WP<5.7. In WP 5.7, the wp_script_attributes and wp_inline_script_attributes filters were introduced, and
 * Rank Math then deemed it preferable to use wp_print_script_tag() and wp_print_inline_script_tag() rather than
 * wp_enqueue_script() and wp_add_inline_script(), respectively. Since Web Worker Offloading requires WP 6.5+, there
 * is no point to integrate with the pre-5.7 code in Rank Math.
 */

/**
 * Filters script attributes to offload Rank Math's GTag script tag to Partytown.
 *
 * @since 0.2.0
 * @access private
 * @link https://github.com/rankmath/seo-by-rank-math/blob/c78adba6f78079f27ff1430fabb75c6ac3916240/includes/modules/analytics/class-gtag.php#L161-L167
 *
 * @param array|mixed $attributes Script attributes.
 * @return array|mixed Filtered script attributes.
 */
function plwwo_rank_math_filter_script_attributes( $attributes ) {
	if ( isset( $attributes['id'] ) && 'google_gtagjs' === $attributes['id'] ) {
		wp_enqueue_script( 'web-worker-offloading' );
		$attributes['type'] = 'text/partytown';
	}
	return $attributes;
}

add_filter( 'wp_script_attributes', 'plwwo_rank_math_filter_script_attributes' );

/**
 * Filters inline script attributes to offload Rank Math's GTag script tag to Partytown.
 *
 * @since 0.2.0
 * @access private
 * @link https://github.com/rankmath/seo-by-rank-math/blob/c78adba6f78079f27ff1430fabb75c6ac3916240/includes/modules/analytics/class-gtag.php#L169-L174
 *
 * @param array|mixed $attributes Script attributes.
 * @return array|mixed Filtered inline script attributes.
 */
function plwwo_rank_math_filter_inline_script_attributes( $attributes ) {
	if ( isset( $attributes['id'] ) && 'google_gtagjs-inline' === $attributes['id'] ) {
		wp_enqueue_script( 'web-worker-offloading' );
		$attributes['type'] = 'text/partytown';
	}
	return $attributes;
}

add_filter( 'wp_inline_script_attributes', 'plwwo_rank_math_filter_inline_script_attributes' );
