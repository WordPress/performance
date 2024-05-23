<?php
/**
 * Metrics storage data.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gets the freshness age (TTL) for a given URL metric.
 *
 * When a URL metric expires it is eligible to be replaced by a newer one if its viewport lies within the same breakpoint.
 *
 * @since 0.1.0
 * @access private
 *
 * @return int Expiration TTL in seconds.
 */
function od_get_url_metric_freshness_ttl(): int {
	/**
	 * Filters the freshness age (TTL) for a given URL metric.
	 *
	 * The freshness TTL must be at least zero, in which it considers URL metrics to always be stale.
	 * In practice, the value should be at least an hour.
	 *
	 * @since 0.1.0
	 *
	 * @param int $ttl Expiration TTL in seconds. Defaults to 1 day.
	 */
	$freshness_ttl = (int) apply_filters( 'od_url_metric_freshness_ttl', DAY_IN_SECONDS );

	if ( $freshness_ttl < 0 ) {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html(
				sprintf(
					/* translators: %s is the TTL freshness */
					__( 'Freshness TTL must be at least zero, but saw "%s".', 'optimization-detective' ),
					$freshness_ttl
				)
			),
			''
		);
		$freshness_ttl = 0;
	}

	return $freshness_ttl;
}

/**
 * Gets the normalized query vars for the current request.
 *
 * This is used as a cache key for stored URL metrics.
 *
 * TODO: For non-singular requests, consider adding the post IDs from The Loop to ensure publishing a new post will invalidate the cache.
 *
 * @since 0.1.0
 * @access private
 *
 * @return array<string, mixed> Normalized query vars.
 */
function od_get_normalized_query_vars(): array {
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
 * Get the URL for the current request.
 *
 * This is essentially the REQUEST_URI prefixed by the scheme and host for the home URL.
 * This is needed in particular due to subdirectory installs.
 *
 * @since 0.1.1
 * @access private
 *
 * @return string Current URL.
 */
function od_get_current_url(): string {
	$parsed_url = wp_parse_url( home_url() );
	if ( ! is_array( $parsed_url ) ) {
		$parsed_url = array();
	}

	if ( empty( $parsed_url['scheme'] ) ) {
		$parsed_url['scheme'] = is_ssl() ? 'https' : 'http';
	}
	if ( ! isset( $parsed_url['host'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$parsed_url['host'] = isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : 'localhost';
	}

	$current_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
	if ( isset( $parsed_url['port'] ) ) {
		$current_url .= ':' . $parsed_url['port'];
	}
	$current_url .= '/';

	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$current_url .= ltrim( wp_unslash( $_SERVER['REQUEST_URI'] ), '/' );
	}
	return esc_url_raw( $current_url );
}

/**
 * Gets slug for URL metrics.
 *
 * A slug is the hash of the normalized query vars.
 *
 * @since 0.1.0
 * @access private
 *
 * @see od_get_normalized_query_vars()
 *
 * @param array<string, mixed> $query_vars Normalized query vars.
 * @return string Slug.
 */
function od_get_url_metrics_slug( array $query_vars ): string {
	return md5( (string) wp_json_encode( $query_vars ) );
}

/**
 * Computes nonce for storing URL metrics for a specific slug.
 *
 * This is used in the REST API to authenticate the storage of new URL metrics from a given URL.
 *
 * @since 0.1.0
 * @access private
 *
 * @see wp_create_nonce()
 * @see od_verify_url_metrics_storage_nonce()
 * @see od_get_url_metrics_slug()
 *
 * @param string $slug Slug (hash of normalized query vars).
 * @param string $url  URL.
 * @return string Nonce.
 */
function od_get_url_metrics_storage_nonce( string $slug, string $url ): string {
	return wp_create_nonce( "store_url_metrics:$slug:$url" );
}

/**
 * Verifies nonce for storing URL metrics for a specific slug.
 *
 * @since 0.1.0
 * @access private
 *
 * @see wp_verify_nonce()
 * @see od_get_url_metrics_storage_nonce()
 * @see od_get_url_metrics_slug()
 *
 * @param string $nonce Nonce.
 * @param string $slug  Slug (hash of normalized query vars).
 * @param String $url   URL.
 * @return bool Whether the nonce is valid.
 */
function od_verify_url_metrics_storage_nonce( string $nonce, string $slug, string $url ): bool {
	return (bool) wp_verify_nonce( $nonce, "store_url_metrics:$slug:$url" );
}

/**
 * Gets the breakpoint max widths to group URL metrics for various viewports.
 *
 * Each number represents the maximum width (inclusive) for a given breakpoint. So if there is one number, 480, then
 * this means there will be two viewport groupings, one for 0<=480, and another >480. If instead there were three
 * provided breakpoints (320, 480, 576) then this means there will be four groups:
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
 * @since 0.1.0
 * @access private
 * @link https://github.com/WordPress/gutenberg/blob/093d52cbfd3e2c140843d3fb91ad3d03330320a5/packages/base-styles/_breakpoints.scss#L11-L13
 *
 * @return int[] Breakpoint max widths, sorted in ascending order.
 */
function od_get_breakpoint_max_widths(): array {
	$function_name = __FUNCTION__;

	$breakpoint_max_widths = array_map(
		static function ( $original_breakpoint ) use ( $function_name ): int {
			$breakpoint = (int) $original_breakpoint;
			if ( PHP_INT_MAX === $breakpoint ) {
				$breakpoint = PHP_INT_MAX - 1;
				_doing_it_wrong(
					esc_html( $function_name ),
					esc_html(
						sprintf(
							/* translators: %s is the actual breakpoint max width */
							__( 'Breakpoint must be less than PHP_INT_MAX, but saw "%s".', 'optimization-detective' ),
							$original_breakpoint
						)
					),
					''
				);
			} elseif ( $breakpoint <= 0 ) {
				$breakpoint = 1;
				_doing_it_wrong(
					esc_html( $function_name ),
					esc_html(
						sprintf(
							/* translators: %s is the actual breakpoint max width */
							__( 'Breakpoint must be greater zero, but saw "%s".', 'optimization-detective' ),
							$original_breakpoint
						)
					),
					''
				);
			}
			return $breakpoint;
		},
		/**
		 * Filters the breakpoint max widths to group URL metrics for various viewports.
		 *
		 * A breakpoint must be greater than zero and less than PHP_INT_MAX.
		 *
		 * @since 0.1.0
		 *
		 * @param int[] $breakpoint_max_widths Max widths for viewport breakpoints. Defaults to [480, 600, 782].
		 */
		(array) apply_filters( 'od_breakpoint_max_widths', array( 480, 600, 782 ) )
	);

	$breakpoint_max_widths = array_unique( $breakpoint_max_widths, SORT_NUMERIC );
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
 * @since 0.1.0
 * @access private
 *
 * @return int Sample size.
 */
function od_get_url_metrics_breakpoint_sample_size(): int {
	/**
	 * Filters the sample size for a breakpoint's URL metrics on a given URL.
	 *
	 * The sample size must be greater than zero.
	 *
	 * @since 0.1.0
	 *
	 * @param int $sample_size Sample size. Defaults to 3.
	 */
	$sample_size = (int) apply_filters( 'od_url_metrics_breakpoint_sample_size', 3 );

	if ( $sample_size <= 0 ) {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html(
				sprintf(
					/* translators: %s is the sample size */
					__( 'Sample size must greater than zero, but saw "%s".', 'optimization-detective' ),
					$sample_size
				)
			),
			''
		);
		$sample_size = 1;
	}

	return $sample_size;
}
