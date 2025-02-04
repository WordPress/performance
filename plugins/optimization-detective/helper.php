<?php
/**
 * Helper functions for Optimization Detective.
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
 * Initializes extensions for Optimization Detective.
 *
 * @since 0.7.0
 * @access private
 */
function od_initialize_extensions(): void {
	/**
	 * Fires when extensions to Optimization Detective can be loaded and initialized.
	 *
	 * @since 0.7.0
	 *
	 * @param string $version Optimization Detective version.
	 */
	do_action( 'od_init', OPTIMIZATION_DETECTIVE_VERSION );
}

/**
 * Generates a media query for the provided minimum and maximum viewport widths.
 *
 * This helper function is available for extensions to leverage when manually printing STYLE rules via
 * {@see OD_HTML_Tag_Processor::append_head_html()} or {@see OD_HTML_Tag_Processor::append_body_html()}
 *
 * @since 0.7.0
 *
 * @param int<0, max>|null $minimum_viewport_width Minimum viewport width (exclusive).
 * @param int<1, max>|null $maximum_viewport_width Maximum viewport width (inclusive).
 * @return non-empty-string|null Media query, or null if the min/max were both unspecified or invalid.
 */
function od_generate_media_query( ?int $minimum_viewport_width, ?int $maximum_viewport_width ): ?string {
	if ( is_int( $minimum_viewport_width ) && is_int( $maximum_viewport_width ) && $minimum_viewport_width >= $maximum_viewport_width ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'The minimum width cannot be greater than or equal to the maximum width.', 'optimization-detective' ), 'Optimization Detective 0.7.0' );
		return null;
	}
	$has_min_width = ( null !== $minimum_viewport_width && $minimum_viewport_width > 0 );
	$has_max_width = ( null !== $maximum_viewport_width && PHP_INT_MAX !== $maximum_viewport_width ); // Note: The use of PHP_INT_MAX is obsolete.
	if ( $has_min_width && $has_max_width ) {
		return sprintf( '(%dpx < width <= %dpx)', $minimum_viewport_width, $maximum_viewport_width );
	} elseif ( $has_min_width ) {
		return sprintf( '(%dpx < width)', $minimum_viewport_width );
	} elseif ( $has_max_width ) {
		return sprintf( '(width <= %dpx)', $maximum_viewport_width );
	} else {
		return null;
	}
}

/**
 * Displays the HTML generator meta tag for the Optimization Detective plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.0
 * @access private
 */
function od_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	$content = 'optimization-detective ' . OPTIMIZATION_DETECTIVE_VERSION;

	// Indicate that the plugin will not be doing anything because the REST API is unavailable.
	if ( od_is_rest_api_unavailable() ) {
		$content .= '; rest_api_unavailable';
	}

	echo '<meta name="generator" content="' . esc_attr( $content ) . '">' . "\n";
}

/**
 * Gets the path to a script or stylesheet.
 *
 * @since 0.9.0
 * @access private
 *
 * @param string      $src_path Source path, relative to plugin root.
 * @param string|null $min_path Minified path. If not supplied, then '.min' is injected before the file extension in the source path.
 * @return string URL to script or stylesheet.
 *
 * @noinspection PhpDocMissingThrowsInspection
 */
function od_get_asset_path( string $src_path, ?string $min_path = null ): string {
	if ( null === $min_path ) {
		// Note: wp_scripts_get_suffix() is not used here because we need access to both the source and minified paths.
		$min_path = (string) preg_replace( '/(?=\.\w+$)/', '.min', $src_path );
	}

	$force_src = false;
	if ( WP_DEBUG && ! file_exists( trailingslashit( __DIR__ ) . $min_path ) ) {
		$force_src = true;
		/**
		 * No WP_Exception is thrown by wp_trigger_error() since E_USER_ERROR is not passed as the error level.
		 *
		 * @noinspection PhpUnhandledExceptionInspection
		 */
		wp_trigger_error(
			__FUNCTION__,
			sprintf(
				/* translators: %s is the minified asset path */
				__( 'Minified asset has not been built: %s', 'optimization-detective' ),
				$min_path
			),
			E_USER_WARNING
		);
	}

	if ( SCRIPT_DEBUG || $force_src ) {
		return $src_path;
	}

	return $min_path;
}

/**
 * Enqueues admin scripts.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string $hook_suffix Current admin page.
 */
function od_enqueue_prime_url_metrics_scripts( string $hook_suffix ): void {
	if ( 'tools_page_od-optimization-detective' === $hook_suffix ) {
		wp_enqueue_script(
			'od-prime-url-metrics',
			plugins_url( od_get_asset_path( 'prime-url-metrics.js' ), __FILE__ ),
			array( 'wp-i18n', 'wp-api-fetch' ),
			OPTIMIZATION_DETECTIVE_VERSION,
			true
		);
	}
}

/**
 * Adds the Optimization Detective menu to the admin menu.
 *
 * @since n.e.x.t
 * @access private
 */
function od_enqueue_block_editor_prime_url_metrics_scripts(): void {
	wp_enqueue_script(
		'od-prime-url-metrics',
		plugins_url( od_get_asset_path( 'prime-url-metrics-block-editor.js' ), __FILE__ ),
		array( 'wp-data', 'wp-api-fetch' ),
		OPTIMIZATION_DETECTIVE_VERSION,
		true
	);
}

/**
 * Gets URLs for priming URL Metrics from sitemap in batches.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param array<string, int> $cursor Cursor to resume from.
 * @return array<string, mixed> Batch of URLs to prime metrics for and the updated cursor.
 */
function od_get_batch_for_iframe_url_metrics_priming( array $cursor ): array {
	// Get the server & its registry of sitemap providers.
	$server   = wp_sitemaps_get_server();
	$registry = $server->registry;

	// All registered providers.
	$providers = array_values( $registry->get_providers() ); // Ensure zero-based index.

	$all_urls        = array();
	$collected_count = 0;

	// Flag to indicate if we should stop collecting further URLs (i.e., we reached $cursor['batch_size']).
	$done = false;

	// Start iterating from the current provider_index forward.
	$providers_count = count( $providers );
	for ( $p = $cursor['provider_index']; $p < $providers_count && ! $done; ) {
		$provider = $providers[ $p ];

		// WordPress providers return an array of strings from get_object_subtypes().
		$subtypes = array_values( $provider->get_object_subtypes() ); // zero-based index.

		// Start from the current subtype_index if resuming.
		$subtypes_count = count( $subtypes );
		for ( $s = ( $p === $cursor['provider_index'] ) ? $cursor['subtype_index'] : 0; $s < $subtypes_count && ! $done; ) {
			// This is a string, e.g. 'post', 'page', etc.
			$subtype = $subtypes[ $s ];

			// Retrieve the max number of pages for this subtype.
			$max_num_pages = $provider->get_max_num_pages( $subtype->name );

			// Start from the current page_number if resuming.
			for ( $page = ( ( $p === $cursor['provider_index'] ) && ( $s === $cursor['subtype_index'] ) ) ? $cursor['page_number'] : 1; $page <= $max_num_pages && ! $done; ++$page ) {
				$url_list = $provider->get_url_list( $page, $subtype->name );
				if ( ! is_array( $url_list ) ) {
					continue;
				}

				// Filter out empty URLs.
				$url_chunk = array_filter( array_column( $url_list, 'loc' ) );

				// We might have partially consumed this page, so skip $cursor['offset_within_page'] items first.
				$current_page_urls = array_slice( $url_chunk, $cursor['offset_within_page'] );

				// Count how many URLs we consumed in this page.
				$consumed_in_this_page = 0;

				// Now collect from current_page_urls until we reach $cursor['batch_size'].
				foreach ( $current_page_urls as $url ) {
					$all_urls[] = $url;
					++$collected_count;
					++$consumed_in_this_page;

					if ( $collected_count >= $cursor['batch_size'] ) {
						// We have our full batch; stop collecting further.
						$done = true;
						break;
					}
				}

				if ( ! $done ) {
					// We consumed this entire page, so if we continue, next time we start at offset 0 of the next page.
					$cursor['page_number']        = $page + 1;
					$cursor['offset_within_page'] = 0;
				} else {
					// We reached the limit in the middle of this page.
					// Figure out how many we used from this page to update the offset properly.
					$extra_consumed = $collected_count - $cursor['batch_size']; // If exactly $cursor['batch_size'], this might be 0 or negative.
					if ( $extra_consumed < 0 ) {
						$extra_consumed = 0;
					}

					$cursor['offset_within_page'] = $cursor['offset_within_page'] + ( $consumed_in_this_page - $extra_consumed );

					// We haven't fully finished this page, so keep the same $cursor['page_number'].
					$cursor['page_number'] = $page;
				}
			} // end for pages

			if ( ! $done ) {
				// If we've finished all pages in this subtype, move to next subtype from the start (page 1, offset 0).
				$cursor['page_number']        = 1;
				$cursor['offset_within_page'] = 0;
			}

			$cursor['subtype_index'] = $s;
			++$s;
		} // end for subtypes

		if ( ! $done ) {
			// If we finished all subtypes in this provider, move to next provider and start at subtype=0, page=1.
			$cursor['subtype_index']      = 0;
			$cursor['page_number']        = 1;
			$cursor['offset_within_page'] = 0;
		}

		$cursor['provider_index'] = $p;
		++$p;
	} // end for providers

	// Prepare next cursor.
	$new_cursor = array(
		'provider_index'     => $cursor['provider_index'],
		'subtype_index'      => $cursor['subtype_index'],
		'page_number'        => $cursor['page_number'],
		'offset_within_page' => $cursor['offset_within_page'],
		'batch_size'         => $cursor['batch_size'],
	);

	return array(
		'urls'   => $all_urls,
		'cursor' => $new_cursor,
	);
}

/**
 * Filter for WP_Query to allow specifying 'post_title__in' => array( 'title1', 'title2', ... ).
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string   $where The WHERE clause of the query.
 * @param WP_Query $query The WP_Query instance.
 */
function od_filter_posts_where_for_titles( string $where, WP_Query $query ): string {
	global $wpdb;

	$titles = (array) $query->get( 'post_title__in', array() );
	$titles = array_filter( $titles );

	if ( 0 === count( $titles ) ) {
		return $where;
	}

	// Safely prepare each title for IN() clause.
	$placeholders = array();
	foreach ( $titles as $title ) {
		$placeholders[] = $wpdb->prepare( '%s', $title );
	}
	$list = implode( ',', $placeholders );

	$where .= " AND {$wpdb->posts}.post_title IN ($list)";
	return $where;
}

/**
 * Fetches od_url_metrics posts of URLs in a single WP_Query.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string[] $urls Array of exact URLs, as stored in post_title of od_url_metrics.
 * @return array<string, OD_URL_Metric_Group_Collection> Map of URL to its OD_URL_Metric_Group_Collection.
 */
function od_get_metrics_by_post_title( array $urls ): array {
	$urls = array_unique( array_filter( $urls ) );
	if ( 0 === count( $urls ) ) {
		return array();
	}

	$results_map = array();

	add_filter( 'posts_where', 'od_filter_posts_where_for_titles', 10, 2 );

	$query = new WP_Query(
		array(
			'post_type'              => OD_URL_Metrics_Post_Type::SLUG,
			'post_status'            => 'publish',
			'post_title__in'         => $urls,
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'fields'                 => 'all',
		)
	);

	remove_filter( 'posts_where', 'od_filter_posts_where_for_titles', 10 );

	foreach ( $query->posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		$results_map[ $post->post_title ] = new OD_URL_Metric_Group_Collection(
			OD_URL_Metrics_Post_Type::get_url_metrics_from_post( $post ),
			md5( '' ), // This is a dummy hash.
			od_get_breakpoint_max_widths(),
			od_get_url_metrics_breakpoint_sample_size(),
			od_get_url_metric_freshness_ttl()
		);
	}
	return $results_map;
}

/**
 * Computes the standard array of breakpoints.
 *
 * @since n.e.x.t
 * @access private
 *
 * @return array<int, array{width: int, height: int}> Array of breakpoints.
 */
function od_get_standard_breakpoints(): array {
	$widths = od_get_breakpoint_max_widths();
	sort( $widths );

	$min_width = $widths[0];
	$max_width = (int) end( $widths ) + 300; // For large screens.
	$widths[]  = $max_width;

	// We need to ensure min is 0.56 (1080/1920) else the height becomes too small.
	$min_ar = max( 0.56, od_get_minimum_viewport_aspect_ratio() );
	// Ensure max is 1.78 (1920/1080) else the height becomes too large.
	$max_ar = min( 1.78, od_get_maximum_viewport_aspect_ratio() );

	// Compute [width => height] for each breakpoint.
	return array_map(
		static function ( $width ) use ( $min_width, $max_width, $min_ar, $max_ar ) {
			// Linear interpolation between max_ar and min_ar based on width.
			$ar = $max_ar - ( ( $max_ar - $min_ar ) * ( ( $width - $min_width ) / ( $max_width - $min_width ) ) );
			$ar = max( $min_ar, min( $max_ar, $ar ) );

			return array(
				'width'  => $width,
				'height' => (int) round( $ar * $width ),
			);
		},
		$widths
	);
}

/**
 * Filters the batch of URLs to only include those that need additional metrics.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param array<string> $urls Array of URLs to filter.
 * @return array<int, array{url: string, breakpoints: array<int, array{width: int, height: int}>}> Filtered batch of URLs.
 */
function od_filter_batch_urls_for_iframe_url_metrics_priming( array $urls ): array {
	$filtered_batch       = array();
	$standard_breakpoints = od_get_standard_breakpoints();
	$group_collections    = od_get_metrics_by_post_title( $urls );

	foreach ( $urls as $url ) {
		$group_collection = $group_collections[ $url ] ?? null;
		if ( ! $group_collection instanceof OD_URL_Metric_Group_Collection ) {
			$filtered_batch[] = array(
				'url'         => $url,
				'breakpoints' => $standard_breakpoints,
			);
			continue;
		}

		if ( $group_collection->is_every_group_populated() ) {
			continue;
		}

		$existing_widths = array();
		foreach ( $group_collection as $group ) {
			if ( ! $group->is_complete() ) {
				foreach ( $group as $url_metric ) {
					$existing_widths[] = $url_metric->get_viewport_width();
				}
			}
		}

		$missing_breakpoints = array();
		foreach ( $standard_breakpoints as $breakpoint ) {
			if ( ! in_array( $breakpoint['width'], $existing_widths, true ) ) {
				$missing_breakpoints[] = $breakpoint;
			}
		}

		if ( count( $missing_breakpoints ) > 0 ) {
			$filtered_batch[] = array(
				'url'         => $url,
				'breakpoints' => $missing_breakpoints,
			);
		}
	}

	return $filtered_batch;
}
