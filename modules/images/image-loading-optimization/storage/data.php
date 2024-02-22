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
 * Unshifts a new URL metric onto an array of URL metrics.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param ILO_URL_Metric[] $url_metrics    Existing URL metrics. Each URL metric is expected to have a timestamp key.
 * @param ILO_URL_Metric   $new_url_metric Validated URL metric. See JSON Schema defined in ilo_register_endpoint().
 * @param int[]            $breakpoints    Breakpoint max widths.
 * @param int              $sample_size    Sample size for URL metrics at a given breakpoint.
 *
 * @return ILO_URL_Metric[] Updated URL metrics.
 */
function ilo_unshift_url_metrics( array $url_metrics, ILO_URL_Metric $new_url_metric, array $breakpoints, int $sample_size ): array {
	array_unshift( $url_metrics, $new_url_metric );
	$grouped_url_metrics = ilo_group_url_metrics_by_breakpoint( $url_metrics, $breakpoints );

	// Make sure there is at most $sample_size number of URL metrics for each breakpoint.
	$grouped_url_metrics = array_map(
		static function ( $breakpoint_url_metrics ) use ( $sample_size ) {
			if ( count( $breakpoint_url_metrics ) > $sample_size ) {

				// Sort URL metrics in descending order by timestamp.
				usort(
					$breakpoint_url_metrics,
					static function ( ILO_URL_Metric $a, ILO_URL_Metric $b ): int {
						return $b->get_timestamp() <=> $a->get_timestamp();
					}
				);

				// Only keep the sample size of the newest URL metrics.
				$breakpoint_url_metrics = array_slice( $breakpoint_url_metrics, 0, $sample_size );
			}
			return $breakpoint_url_metrics;
		},
		$grouped_url_metrics
	);

	return array_merge( ...$grouped_url_metrics );
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

/**
 * Groups URL metrics by breakpoint.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param ILO_URL_Metric[] $url_metrics URL metrics.
 * @param int[]            $breakpoints Viewport breakpoint max widths, sorted in ascending order.
 * @return array<int, ILO_URL_Metric[]> URL metrics grouped by breakpoint. The array keys are the minimum widths for a viewport to lie within
 *                                      the breakpoint. The returned array is always one larger than the provided array of breakpoints, since
 *                                      the breakpoints reflect the max inclusive boundaries whereas the return value is the groups of page
 *                                      metrics with viewports on either side of the breakpoint boundaries.
 */
function ilo_group_url_metrics_by_breakpoint( array $url_metrics, array $breakpoints ): array {

	// Convert breakpoint max widths into viewport minimum widths.
	$minimum_viewport_widths = array_map(
		static function ( $breakpoint ) {
			return $breakpoint + 1;
		},
		$breakpoints
	);

	$grouped = array_fill_keys( array_merge( array( 0 ), $minimum_viewport_widths ), array() );

	foreach ( $url_metrics as $url_metric ) {
		$current_minimum_viewport = 0;
		foreach ( $minimum_viewport_widths as $viewport_minimum_width ) {
			if ( $url_metric->get_viewport()['width'] > $viewport_minimum_width ) {
				$current_minimum_viewport = $viewport_minimum_width;
			} else {
				break;
			}
		}

		$grouped[ $current_minimum_viewport ][] = $url_metric;
	}
	return $grouped;
}

/**
 * Gets the LCP element for each breakpoint.
 *
 * The array keys are the minimum viewport width required for the element to be LCP. If there are URL metrics for a
 * given breakpoint and yet there is no supported LCP element, then the array value is `false`. (Currently only IMG is
 * a supported LCP element.) If there is a supported LCP element at the breakpoint, then the array value is an array
 * representing that element, including its breadcrumbs. If two adjoining breakpoints have the same value, then the
 * latter is dropped.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param array<int, ILO_URL_Metric[]> $grouped_url_metrics URL metrics grouped by breakpoint. See `ilo_group_url_metrics_by_breakpoint()`.
 * @return array LCP elements keyed by its minimum viewport width. If there is no supported LCP element at a breakpoint, then `false` is used.
 */
function ilo_get_lcp_elements_by_minimum_viewport_widths( array $grouped_url_metrics ): array {

	$lcp_element_by_viewport_minimum_width = array();
	foreach ( $grouped_url_metrics as $viewport_minimum_width => $breakpoint_url_metrics ) {

		// The following arrays all share array indices.
		$seen_breadcrumbs   = array();
		$breadcrumb_counts  = array();
		$breadcrumb_element = array();

		foreach ( $breakpoint_url_metrics as $breakpoint_url_metric ) {
			foreach ( $breakpoint_url_metric->get_elements() as $element ) {
				if ( ! $element['isLCP'] ) {
					continue;
				}

				$i = array_search( $element['xpath'], $seen_breadcrumbs, true );
				if ( false === $i ) {
					$i                       = count( $seen_breadcrumbs );
					$seen_breadcrumbs[ $i ]  = $element['xpath'];
					$breadcrumb_counts[ $i ] = 0;
				}

				$breadcrumb_counts[ $i ] += 1;
				$breadcrumb_element[ $i ] = $element;
				break; // We found the LCP element for the URL metric, go to the next URL metric.
			}
		}

		// Now sort by the breadcrumb counts in descending order, so the remaining first key is the most common breadcrumb.
		if ( $seen_breadcrumbs ) {
			arsort( $breadcrumb_counts );
			$most_common_breadcrumb_index = key( $breadcrumb_counts );

			$lcp_element_by_viewport_minimum_width[ $viewport_minimum_width ] = $breadcrumb_element[ $most_common_breadcrumb_index ];
		} elseif ( ! empty( $breakpoint_url_metrics ) ) {
			$lcp_element_by_viewport_minimum_width[ $viewport_minimum_width ] = false; // No LCP image at this breakpoint.
		}
	}

	// Now merge the breakpoints when there is an LCP element common between them.
	$prev_lcp_element = null;
	return array_filter(
		$lcp_element_by_viewport_minimum_width,
		static function ( $lcp_element ) use ( &$prev_lcp_element ) {
			$include = (
				// First element in list.
				null === $prev_lcp_element
				||
				( is_array( $prev_lcp_element ) && is_array( $lcp_element )
					?
					// This breakpoint and previous breakpoint had LCP element, and they were not the same element.
					$prev_lcp_element['xpath'] !== $lcp_element['xpath']
					:
					// This LCP element and the last LCP element were not the same. In this case, either variable may be
					// false or an array, but both cannot be an array. If both are false, we don't want to include since
					// it is the same. If one is an array and the other is false, then do want to include because this
					// indicates a difference at this breakpoint.
					$prev_lcp_element !== $lcp_element
				)
			);
			$prev_lcp_element = $lcp_element;
			return $include;
		}
	);
}

/**
 * Gets needed minimum viewport widths.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param ILO_URL_Metric[] $url_metrics URL metrics.
 * @param float            $current_time           Current time as returned by `microtime(true)`.
 * @param int[]            $breakpoint_max_widths  Breakpoint max widths.
 * @param int              $sample_size            Sample size for viewports in a breakpoint.
 * @param int              $freshness_ttl          Freshness TTL for a URL metric.
 * @return array<int, array{int, bool}> Array of tuples mapping minimum viewport width to whether URL metric(s) are needed.
 */
function ilo_get_needed_minimum_viewport_widths( array $url_metrics, float $current_time, array $breakpoint_max_widths, int $sample_size, int $freshness_ttl ): array {
	$metrics_by_breakpoint          = ilo_group_url_metrics_by_breakpoint( $url_metrics, $breakpoint_max_widths );
	$needed_minimum_viewport_widths = array();
	foreach ( $metrics_by_breakpoint as $minimum_viewport_width => $viewport_url_metrics ) {
		$needs_url_metrics = false;
		if ( count( $viewport_url_metrics ) < $sample_size ) {
			$needs_url_metrics = true;
		} else {
			foreach ( $viewport_url_metrics as $url_metric ) {
				if ( $url_metric->get_timestamp() + $freshness_ttl < $current_time ) {
					$needs_url_metrics = true;
					break;
				}
			}
		}
		$needed_minimum_viewport_widths[] = array(
			$minimum_viewport_width,
			$needs_url_metrics,
		);
	}

	return $needed_minimum_viewport_widths;
}
