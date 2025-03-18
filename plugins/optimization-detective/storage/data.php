<?php
/**
 * Metrics storage data.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Gets the freshness age (TTL) for a given URL Metric.
 *
 * When a URL Metric expires it is eligible to be replaced by a newer one if its viewport lies within the same breakpoint.
 *
 * @since 0.1.0
 * @access private
 *
 * @return int<0, max> Expiration TTL in seconds.
 */
function od_get_url_metric_freshness_ttl(): int {
	/**
	 * Filters the freshness age (TTL) for a given URL Metric.
	 *
	 * The freshness TTL must be at least zero, in which it considers URL Metrics to always be stale.
	 * In practice, the value should be at least an hour.
	 *
	 * @since 0.1.0
	 *
	 * @param int $ttl Expiration TTL in seconds. Defaults to 1 week.
	 */
	$freshness_ttl = (int) apply_filters( 'od_url_metric_freshness_ttl', WEEK_IN_SECONDS );

	if ( $freshness_ttl < 0 ) {
		_doing_it_wrong(
			esc_html( "Filter: 'od_url_metric_freshness_ttl'" ),
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
 * This is used as a cache key for stored URL Metrics.
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

	if ( ! isset( $parsed_url['scheme'] ) ) {
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

	// TODO: We should be able to assert that this returns an non-empty-string.
	return esc_url_raw( $current_url );
}

/**
 * Gets slug for URL Metrics.
 *
 * A slug is the hash of the normalized query vars.
 *
 * @since 0.1.0
 * @access private
 *
 * @see od_get_normalized_query_vars()
 *
 * @param array<string, mixed> $query_vars Normalized query vars.
 * @return non-empty-string Slug.
 */
function od_get_url_metrics_slug( array $query_vars ): string {
	return md5( (string) wp_json_encode( $query_vars ) );
}

/**
 * Gets the current template for a block theme or a classic theme.
 *
 * @since 0.9.0
 * @access private
 *
 * @global string|null $_wp_current_template_id Current template ID.
 * @global string|null $template                Template file path.
 *
 * @return string|WP_Block_Template|null Template.
 */
function od_get_current_theme_template() {
	global $template, $_wp_current_template_id;

	if ( wp_is_block_theme() && isset( $_wp_current_template_id ) ) {
		$block_template = get_block_template( $_wp_current_template_id, 'wp_template' );
		if ( $block_template instanceof WP_Block_Template ) {
			return $block_template;
		}
	}
	if ( isset( $template ) && is_string( $template ) ) {
		return basename( $template );
	}
	return null;
}

/**
 * Gets the current ETag for URL Metrics.
 *
 * Generates a hash based on the IDs of registered tag visitors, the queried object,
 * posts in The Loop, and theme information in the current environment. This ETag
 * is used to assess if the URL Metrics are stale when its value changes.
 *
 * @since 0.9.0
 * @access private
 *
 * @param OD_Tag_Visitor_Registry       $tag_visitor_registry Tag visitor registry.
 * @param WP_Query|null                 $wp_query             The WP_Query instance.
 * @param string|WP_Block_Template|null $current_template     The current template being used.
 * @return non-empty-string Current ETag.
 */
function od_get_current_url_metrics_etag( OD_Tag_Visitor_Registry $tag_visitor_registry, ?WP_Query $wp_query, $current_template ): string {
	$queried_object      = $wp_query instanceof WP_Query ? $wp_query->get_queried_object() : null;
	$queried_object_data = array(
		'id'   => null,
		'type' => null,
	);

	if ( $queried_object instanceof WP_Post ) {
		$queried_object_data['id']                = $queried_object->ID;
		$queried_object_data['type']              = 'post';
		$queried_object_data['post_modified_gmt'] = $queried_object->post_modified_gmt;
	} elseif ( $queried_object instanceof WP_Term ) {
		$queried_object_data['id']   = $queried_object->term_id;
		$queried_object_data['type'] = 'term';
	} elseif ( $queried_object instanceof WP_User ) {
		$queried_object_data['id']   = $queried_object->ID;
		$queried_object_data['type'] = 'user';
	} elseif ( $queried_object instanceof WP_Post_Type ) {
		$queried_object_data['type'] = $queried_object->name;
	}

	$active_plugins = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$active_plugins = array_unique(
			array_merge(
				$active_plugins,
				array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) )
			)
		);
	}
	sort( $active_plugins );

	$data = array(
		'xpath_version'    => 2, // Bump whenever a major change to the XPath format occurs so that new URL Metrics are proactively gathered.
		'tag_visitors'     => array_keys( iterator_to_array( $tag_visitor_registry ) ),
		'queried_object'   => $queried_object_data,
		'queried_posts'    => array_filter(
			array_map(
				static function ( $post ): ?array {
					if ( is_int( $post ) ) {
						$post = get_post( $post );
					}
					if ( ! ( $post instanceof WP_Post ) ) {
						return null;
					}
					return array(
						'id'                => $post->ID,
						'post_modified_gmt' => $post->post_modified_gmt,
					);
				},
				( $wp_query instanceof WP_Query && $wp_query->post_count > 0 ) ? $wp_query->posts : array()
			)
		),
		'active_theme'     => array(
			'template'   => array(
				'name'    => get_template(),
				'version' => wp_get_theme( get_template() )->get( 'Version' ),
			),
			'stylesheet' => array(
				'name'    => get_stylesheet(),
				'version' => wp_get_theme()->get( 'Version' ),
			),
		),
		'active_plugins'   => $active_plugins,
		'current_template' => $current_template instanceof WP_Block_Template ? get_object_vars( $current_template ) : $current_template,
	);

	/**
	 * Filters the data that goes into computing the current ETag for URL Metrics.
	 *
	 * @since 0.9.0
	 *
	 * @param array<string, mixed> $data Data.
	 */
	$data = (array) apply_filters( 'od_current_url_metrics_etag_data', $data );

	return md5( (string) wp_json_encode( $data ) );
}

/**
 * Computes HMAC for storing URL Metrics for a specific slug.
 *
 * This is used in the REST API to authenticate the storage of new URL Metrics from a given URL.
 *
 * @since 0.8.0
 * @since 0.9.0 Introduced the `$current_etag` parameter.
 * @access private
 *
 * @see od_verify_url_metrics_storage_hmac()
 * @see od_get_url_metrics_slug()
 *
 * @param non-empty-string  $slug                Slug (hash of normalized query vars).
 * @param non-empty-string  $current_etag        Current ETag.
 * @param string            $url                 URL.
 * @param positive-int|null $cache_purge_post_id Cache purge post ID.
 * @return non-empty-string HMAC.
 */
function od_get_url_metrics_storage_hmac( string $slug, string $current_etag, string $url, ?int $cache_purge_post_id = null ): string {
	$action = "store_url_metric:$slug:$current_etag:$url:$cache_purge_post_id";

	/**
	 * HMAC.
	 *
	 * @var non-empty-string $hmac
	 */
	$hmac = wp_hash( $action, 'nonce' );
	return $hmac;
}

/**
 * Verifies HMAC for storing URL Metrics for a specific slug.
 *
 * @since 0.8.0
 * @since 0.9.0 Introduced the `$current_etag` parameter.
 * @access private
 *
 * @see od_get_url_metrics_storage_hmac()
 * @see od_get_url_metrics_slug()
 *
 * @param non-empty-string  $hmac                HMAC.
 * @param non-empty-string  $slug                Slug (hash of normalized query vars).
 * @param non-empty-string  $current_etag        Current ETag.
 * @param string            $url                 URL.
 * @param positive-int|null $cache_purge_post_id Cache purge post ID.
 * @return bool Whether the HMAC is valid.
 */
function od_verify_url_metrics_storage_hmac( string $hmac, string $slug, string $current_etag, string $url, ?int $cache_purge_post_id = null ): bool {
	return hash_equals( od_get_url_metrics_storage_hmac( $slug, $current_etag, $url, $cache_purge_post_id ), $hmac );
}

/**
 * Gets the minimum allowed viewport aspect ratio for URL Metrics.
 *
 * @since 0.6.0
 * @access private
 *
 * @return float Minimum viewport aspect ratio for URL Metrics.
 */
function od_get_minimum_viewport_aspect_ratio(): float {
	/**
	 * Filters the minimum allowed viewport aspect ratio for URL Metrics.
	 *
	 * The 0.4 default value is intended to accommodate the phone with the greatest known aspect
	 * ratio at 21:9 when rotated 90 degrees to 9:21 (0.429).
	 *
	 * @since 0.6.0
	 *
	 * @param float $minimum_viewport_aspect_ratio Minimum viewport aspect ratio.
	 */
	return (float) apply_filters( 'od_minimum_viewport_aspect_ratio', 0.4 );
}

/**
 * Gets the maximum allowed viewport aspect ratio for URL Metrics.
 *
 * @since 0.6.0
 * @access private
 *
 * @return float Maximum viewport aspect ratio for URL Metrics.
 */
function od_get_maximum_viewport_aspect_ratio(): float {
	/**
	 * Filters the maximum allowed viewport aspect ratio for URL Metrics.
	 *
	 * The 2.5 default value is intended to accommodate the phone with the greatest known aspect
	 * ratio at 21:9 (2.333).
	 *
	 * @since 0.6.0
	 *
	 * @param float $maximum_viewport_aspect_ratio Maximum viewport aspect ratio.
	 */
	return (float) apply_filters( 'od_maximum_viewport_aspect_ratio', 2.5 );
}

/**
 * Gets the breakpoint max widths to group URL Metrics for various viewports.
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
 * This array may be empty in which case there are no responsive breakpoints and all URL Metrics are collected in a
 * single group.
 *
 * @since 0.1.0
 * @access private
 * @link https://github.com/WordPress/gutenberg/blob/093d52cbfd3e2c140843d3fb91ad3d03330320a5/packages/base-styles/_breakpoints.scss#L11-L13
 *
 * @return positive-int[] Breakpoint max widths, sorted in ascending order.
 */
function od_get_breakpoint_max_widths(): array {
	$breakpoint_max_widths = array_map(
		static function ( $original_breakpoint ): int {
			$breakpoint = $original_breakpoint;
			if ( $breakpoint <= 0 ) {
				$breakpoint = 1;
				_doing_it_wrong(
					esc_html( "Filter: 'od_breakpoint_max_widths'" ),
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
		 * Filters the breakpoint max widths to group URL Metrics for various viewports.
		 *
		 * A breakpoint must be greater than zero. This array may be empty in which case there
		 * are no responsive breakpoints and all URL Metrics are collected in a single group.
		 *
		 * @since 0.1.0
		 *
		 * @param positive-int[] $breakpoint_max_widths Max widths for viewport breakpoints. Defaults to [480, 600, 782].
		 */
		array_map( 'intval', (array) apply_filters( 'od_breakpoint_max_widths', array( 480, 600, 782 ) ) )
	);

	$breakpoint_max_widths = array_unique( $breakpoint_max_widths, SORT_NUMERIC );
	sort( $breakpoint_max_widths );
	return $breakpoint_max_widths;
}

/**
 * Gets the sample size for a breakpoint's URL Metrics on a given URL.
 *
 * A breakpoint divides URL Metrics for viewports which are smaller and those which are larger. Given the default
 * sample size of 3 and there being just a single breakpoint (480) by default, for a given URL, there would be a maximum
 * total of 6 URL Metrics stored for a given URL: 3 for mobile and 3 for desktop.
 *
 * @since 0.1.0
 * @access private
 *
 * @return int<1, max> Sample size.
 */
function od_get_url_metrics_breakpoint_sample_size(): int {
	/**
	 * Filters the sample size for a breakpoint's URL Metrics on a given URL.
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
			esc_html( "Filter: 'od_url_metrics_breakpoint_sample_size'" ),
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
