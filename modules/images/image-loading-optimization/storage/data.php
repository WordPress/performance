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
 * Gets the expiration age for a given page metric.
 *
 * When a page metric expires it is eligible to be replaced by a newer one.
 *
 * TODO: However, we keep viewport-specific page metrics regardless of TTL.
 *
 * @return int Expiration age in seconds.
 */
function ilo_get_page_metric_ttl() {
	/**
	 * Filters the expiration age for a given page metric.
	 *
	 * @param int $ttl TTL.
	 */
	return (int) apply_filters( 'ilo_page_metric_ttl', MONTH_IN_SECONDS );
}

/**
 * Gets the normalized current URL.
 *
 * TODO: This will need to be made more robust for non-singular URLs. What about multi-faceted archives with multiple taxonomies and date parameters?
 *
 * @return string Normalized current URL.
 */
function ilo_get_normalized_current_url() {
	if ( is_singular() ) {
		$url = wp_get_canonical_url();
		if ( $url ) {
			return $url;
		}
	}

	$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

	$scheme = is_ssl() ? 'https' : 'http';
	$host   = strtok( $_SERVER['HTTP_HOST'], ':' ); // Use of strtok() since wp-env erroneously includes port in host.
	$port   = (int) $_SERVER['SERVER_PORT'];
	$path   = '';
	$query  = '';
	if ( preg_match( '%(^.+?)(?:\?([^#]+))?%', wp_unslash( $_SERVER['REQUEST_URI'] ), $matches ) ) {
		if ( ! empty( $matches[1] ) ) {
			$path = $matches[1];
		}
		if ( ! empty( $matches[2] ) ) {
			$query = $matches[2];
		}
	}
	if ( $query ) {
		$removable_query_args   = wp_removable_query_args();
		$removable_query_args[] = 'fbclid';

		$old_query_args = array();
		$new_query_args = array();
		wp_parse_str( $query, $old_query_args );
		foreach ( $old_query_args as $key => $value ) {
			if (
				str_starts_with( 'utm_', $key ) ||
				in_array( $key, $removable_query_args, true )
			) {
				continue;
			}
			$new_query_args[ $key ] = $value;
		}
		asort( $new_query_args );
		$query = build_query( $new_query_args );
	}

	// Normalize open-ended URLs.
	if ( is_404() ) {
		$path  = $home_path;
		$query = 'error=404';
	} elseif ( is_search() ) {
		$path  = $home_path;
		$query = 's={}';
	}

	// Rebuild the URL.
	$url = $scheme . '://' . $host;
	if ( 80 !== $port && 443 !== $port ) {
		$url .= ":{$port}";
	}
	$url .= $path;
	if ( $query ) {
		$url .= "?{$query}";
	}

	return $url;
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
 * @return array Grouped page metrics.
 */
function ilo_group_page_metrics_by_breakpoint( array $page_metrics, array $breakpoints ) {
	$max_index          = count( $breakpoints );
	$groups             = array_fill( 0, $max_index + 1, array() );
	$largest_breakpoint = $breakpoints[ $max_index - 1 ];
	foreach ( $page_metrics as $page_metric ) {
		if ( ! isset( $page_metric['viewport']['width'] ) ) {
			continue;
		}
		$viewport_width = $page_metric['viewport']['width'];
		if ( $viewport_width > $largest_breakpoint ) {
			$groups[ $max_index ][] = $page_metric;
		}
		foreach ( $breakpoints as $group => $breakpoint ) {
			if ( $viewport_width <= $breakpoint ) {
				$groups[ $group ][] = $page_metric;
			}
		}
	}
	return $groups;
}
