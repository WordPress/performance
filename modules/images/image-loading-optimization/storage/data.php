<?php
/**
 * Metrics storage data.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gets the freshness age (TTL) for a given page metric.
 *
 * When a page metric expires it is eligible to be replaced by a newer one if its viewport lies within the same breakpoint.
 *
 * @return int Expiration TTL in seconds.
 */
function ilo_get_page_metric_freshness_ttl() {
	/**
	 * Filters the freshness age (TTL) for a given page metric.
	 *
	 * @param int $ttl Expiration TTL in seconds.
	 */
	return (int) apply_filters( 'ilo_page_metric_freshness_ttl', DAY_IN_SECONDS );
}

/**
 * Get the URL for the current request.
 *
 * This is essentially the REQUEST_URI prefixed by the scheme and host for the home URL.
 * This is needed in particular due to subdirectory installs.
 *
 * @return string Current URL.
 */
function ilo_get_current_url() {
	$parsed_url = wp_parse_url( home_url() );

	if ( ! is_array( $parsed_url ) ) {
		$parsed_url = array();
	}

	if ( empty( $parsed_url['scheme'] ) ) {
		$parsed_url['scheme'] = is_ssl() ? 'https' : 'http';
	}
	if ( ! isset( $parsed_url['host'] ) ) {
		$parsed_url['host'] = isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : 'localhost';
	}

	$current_url = $parsed_url['scheme'] . '://';
	if ( isset( $parsed_url['user'] ) ) {
		$current_url .= $parsed_url['user'];
		if ( isset( $parsed_url['pass'] ) ) {
			$current_url .= ':' . $parsed_url['pass'];
		}
		$current_url .= '@';
	}
	$current_url .= $parsed_url['host'];
	if ( isset( $parsed_url['port'] ) ) {
		$current_url .= ':' . $parsed_url['port'];
	}
	$current_url .= '/';

	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$current_url .= ltrim( wp_unslash( $_SERVER['REQUEST_URI'] ), '/' );
	}
	return esc_url_raw( $current_url );
}

/**
 * Gets the normalized query vars for the current request.
 *
 * This is used as a cache key for stored page metrics.
 *
 * @return array Normalized query vars.
 */
function ilo_get_normalized_query_vars() {
	global $wp;

	// Note that the order of this array is naturally normalized since it is
	// assembled by iterating over public_query_vars.
	$normalized_query_vars = $wp->query_vars;

	// Normalize unbounded query vars.
	if ( is_404() ) {
		$normalized_query_vars = array(
			'error' => 404,
		);
	} elseif ( array_key_exists( 's', $normalized_query_vars ) ) {
		$normalized_query_vars['s'] = '...';
	}

	return $normalized_query_vars;
}

/**
 * Gets slug for page metrics.
 *
 * @see ilo_get_normalized_query_vars()
 *
 * @param array $query_vars Normalized query vars.
 * @return string Slug.
 */
function ilo_get_page_metrics_slug( $query_vars ) {
	return md5( wp_json_encode( $query_vars ) );
}

/**
 * Compute nonce for storing page metrics for a specific slug.
 *
 * This is used in the REST API to authenticate the storage of new page metrics from a given URL.
 *
 * @see wp_create_nonce()
 * @see ilo_verify_page_metrics_storage_nonce()
 *
 * @param string $slug Page metrics slug.
 * @return string Nonce.
 */
function ilo_get_page_metrics_storage_nonce( $slug ) {
	return wp_create_nonce( "store_page_metrics:{$slug}" );
}

/**
 * Verify nonce for storing page metrics for a specific slug.
 *
 * @see wp_verify_nonce()
 * @see ilo_get_page_metrics_storage_nonce()
 *
 * @param string $nonce Page metrics storage nonce.
 * @param string $slug  Page metrics slug.
 * @return int|false 1 if the nonce is valid and generated between 0-12 hours ago,
 *                   2 if the nonce is valid and generated between 12-24 hours ago.
 *                   False if the nonce is invalid.
 */
function ilo_verify_page_metrics_storage_nonce( $nonce, $slug ) {
	return wp_verify_nonce( $nonce, "store_page_metrics:{$slug}" );
}

/**
 * Unshift a new page metric onto an array of page metrics.
 *
 * @param array $page_metrics          Page metrics.
 * @param array $validated_page_metric Validated page metric.
 * @return array Updated page metrics.
 */
function ilo_unshift_page_metrics( $page_metrics, $validated_page_metric ) {
	array_unshift( $page_metrics, $validated_page_metric );
	$breakpoints          = ilo_get_breakpoint_max_widths();
	$sample_size          = ilo_get_page_metrics_breakpoint_sample_size();
	$grouped_page_metrics = ilo_group_page_metrics_by_breakpoint( $page_metrics, $breakpoints );

	foreach ( $grouped_page_metrics as &$breakpoint_page_metrics ) {
		if ( count( $breakpoint_page_metrics ) > $sample_size ) {
			$breakpoint_page_metrics = array_slice( $breakpoint_page_metrics, 0, $sample_size );
		}
	}

	return array_merge( ...$grouped_page_metrics );
}

/**
 * Gets the breakpoint max widths to group page metrics for various viewports.
 *
 * Each max with represents the maximum width (inclusive) for a given breakpoint. So if there is one number, 480, then
 * this means there will be two viewport groupings, one for 0<=480, and another >480. If instead there were three
 * provided breakpoints (320, 480, 576) then this means there will be four viewport groupings:
 *
 *  1. 0-320 (small smartphone)
 *  2. 321-480 (normal smartphone)
 *  3. 481-576 (phablets)
 *  4. >576 (desktop)
 *
 * @return int[] Breakpoint max widths, sorted in ascending order.
 */
function ilo_get_breakpoint_max_widths() {

	/**
	 * Filters the breakpoint max widths to group page metrics for various viewports.
	 *
	 * @param int[] $breakpoint_max_widths Max widths for viewport breakpoints.
	 */
	$breakpoint_max_widths = array_map(
		static function ( $breakpoint_max_width ) {
			return (int) $breakpoint_max_width;
		},
		(array) apply_filters( 'ilo_breakpoint_max_widths', array( 480 ) )
	);

	sort( $breakpoint_max_widths );
	return $breakpoint_max_widths;
}

/**
 * Gets the sample size for a breakpoint's page metrics on a given URL.
 *
 * A breakpoint divides page metrics for viewports which are smaller and those which are larger. Given the default
 * sample size of 3 and there being just a single breakpoint (480) by default, for a given URL, there would be a maximum
 * total of 6 page metrics stored for a given URL: 3 for mobile and 3 for desktop.
 *
 * @return int Sample size.
 */
function ilo_get_page_metrics_breakpoint_sample_size() {
	/**
	 * Filters the sample size for a breakpoint's page metrics on a given URL.
	 *
	 * @param int $sample_size Sample size.
	 */
	return (int) apply_filters( 'ilo_page_metrics_breakpoint_sample_size', 3 );
}

/**
 * Groups page metrics by breakpoint.
 *
 * @param array $page_metrics Page metrics.
 * @param int[] $breakpoints  Viewport breakpoint max widths, sorted in ascending order.
 * @return array Page metrics grouped by breakpoint. The array keys are the minimum widths for a viewport to lie within
 *               the breakpoint. The returned array is always one larger than the provided array of breakpoints, since
 *               the breakpoints reflect the max inclusive boundaries whereas the return value is the groups of page
 *               metrics with viewports on either side of the breakpoint boundaries.
 */
function ilo_group_page_metrics_by_breakpoint( array $page_metrics, array $breakpoints ) {

	// Convert breakpoint max widths into viewport minimum widths.
	$viewport_minimum_widths = array_map(
		static function ( $breakpoint ) {
			return $breakpoint + 1;
		},
		$breakpoints
	);

	$grouped = array_fill_keys( array_merge( array( 0 ), $viewport_minimum_widths ), array() );

	foreach ( $page_metrics as $page_metric ) {
		if ( ! isset( $page_metric['viewport']['width'] ) ) {
			continue;
		}
		$viewport_width = $page_metric['viewport']['width'];

		$current_minimum_viewport = 0;
		foreach ( $viewport_minimum_widths as $viewport_minimum_width ) {
			if ( $viewport_width > $viewport_minimum_width ) {
				$current_minimum_viewport = $viewport_minimum_width;
			} else {
				break;
			}
		}

		$grouped[ $current_minimum_viewport ][] = $page_metric;
	}
	return $grouped;
}
