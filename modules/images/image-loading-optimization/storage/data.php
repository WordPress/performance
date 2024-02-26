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
 * Gets the freshness age (TTL) for a given URL metric.
 *
 * When a URL metric expires it is eligible to be replaced by a newer one if its viewport lies within the same breakpoint.
 *
 * @since n.e.x.t
 * @access private
 *
 * @return int Expiration TTL in seconds.
 */
function ilo_get_url_metric_freshness_ttl(): int {
	/**
	 * Filters the freshness age (TTL) for a given URL metric.
	 *
	 * @since n.e.x.t
	 *
	 * @param int $ttl Expiration TTL in seconds.
	 */
	return (int) apply_filters( 'ilo_url_metric_freshness_ttl', DAY_IN_SECONDS );
}

/**
 * Gets the normalized query vars for the current request.
 *
 * This is used as a cache key for stored URL metrics.
 *
 * TODO: For non-singular requests, consider adding the post IDs from The Loop to ensure publishing a new post will invalidate the cache.
 *
 * @since n.e.x.t
 * @access private
 *
 * @return array Normalized query vars.
 */
function ilo_get_normalized_query_vars(): array {
	global $wp;

	// Note that the order of this array is naturally normalized since it is
	// assembled by iterating over public_query_vars.
	$normalized_query_vars = $wp->query_vars;

	// Normalize unbounded query vars.
	if ( is_404() ) {
		$normalized_query_vars = array(
			'error' => 404,
		);
	}

	// Vary URL metrics by whether the user is logged in since additional elements may be present.
	if ( is_user_logged_in() ) {
		$normalized_query_vars['user_logged_in'] = true;
	}

	return $normalized_query_vars;
}

/**
 * Gets slug for URL metrics.
 *
 * @since n.e.x.t
 * @access private
 *
 * @see ilo_get_normalized_query_vars()
 *
 * @param array $query_vars Normalized query vars.
 * @return string Slug.
 */
function ilo_get_url_metrics_slug( array $query_vars ): string {
	return md5( wp_json_encode( $query_vars ) );
}

/**
 * Computes nonce for storing URL metrics for a specific slug.
 *
 * This is used in the REST API to authenticate the storage of new URL metrics from a given URL.
 *
 * @since n.e.x.t
 * @access private
 *
 * @see wp_create_nonce()
 * @see ilo_verify_url_metrics_storage_nonce()
 *
 * @param string $slug URL metrics slug.
 * @return string Nonce.
 */
function ilo_get_url_metrics_storage_nonce( string $slug ): string {
	return wp_create_nonce( "store_url_metrics:$slug" );
}

/**
 * Verifies nonce for storing URL metrics for a specific slug.
 *
 * @since n.e.x.t
 * @access private
 *
 * @see wp_verify_nonce()
 * @see ilo_get_url_metrics_storage_nonce()
 *
 * @param string $nonce URL metrics storage nonce.
 * @param string $slug  URL metrics slug.
 * @return bool Whether the nonce is valid.
 */
function ilo_verify_url_metrics_storage_nonce( string $nonce, string $slug ): bool {
	return (bool) wp_verify_nonce( $nonce, "store_url_metrics:$slug" );
}

/**
 * Gets the breakpoint max widths to group URL metrics for various viewports.
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
 * The default breakpoints are reused from Gutenberg where the _breakpoints.scss file includes these variables:
 *
 *     $break-medium: 782px; // adminbar goes big
 *     $break-small: 600px;
 *     $break-mobile: 480px;
 *
 * These breakpoints appear to be used the most in media queries that affect frontend styles.
 *
 * @since n.e.x.t
 * @access private
 * @link https://github.com/WordPress/gutenberg/blob/093d52cbfd3e2c140843d3fb91ad3d03330320a5/packages/base-styles/_breakpoints.scss#L11-L13
 *
 * @return int[] Breakpoint max widths, sorted in ascending order.
 */
function ilo_get_breakpoint_max_widths(): array {

	$breakpoint_max_widths = array_map(
		static function ( $breakpoint_max_width ) {
			return (int) $breakpoint_max_width;
		},
		/**
		 * Filters the breakpoint max widths to group URL metrics for various viewports.
		 *
		 * @since n.e.x.t
		 *
		 * @param int[] $breakpoint_max_widths Max widths for viewport breakpoints.
		 */
		(array) apply_filters( 'ilo_breakpoint_max_widths', array( 480, 600, 782 ) )
	);

	sort( $breakpoint_max_widths );
	return $breakpoint_max_widths;
}

/**
 * Gets the sample size for a breakpoint's URL metrics on a given URL.
 *
 * A breakpoint divides URL metrics for viewports which are smaller and those which are larger. Given the default
 * sample size of 3 and there being just a single breakpoint (480) by default, for a given URL, there would be a maximum
 * total of 6 URL metrics stored for a given URL: 3 for mobile and 3 for desktop.
 *
 * @since n.e.x.t
 * @access private
 *
 * @return int Sample size.
 */
function ilo_get_url_metrics_breakpoint_sample_size(): int {
	/**
	 * Filters the sample size for a breakpoint's URL metrics on a given URL.
	 *
	 * @since n.e.x.t
	 *
	 * @param int $sample_size Sample size.
	 */
	return (int) apply_filters( 'ilo_url_metrics_breakpoint_sample_size', 3 );
}
