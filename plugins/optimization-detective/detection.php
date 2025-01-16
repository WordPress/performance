<?php
/**
 * Detection for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Obtains the ID for a post related to this response so that page caches can be told to invalidate their cache.
 *
 * If the queried object for the response is a post, then that post's ID is used. Otherwise, it uses the ID of the first
 * post in The Loop.
 *
 * When the queried object is a post (e.g. is_singular, is_posts_page, is_front_page w/ show_on_front=page), then this
 * is the perfect match. A page caching plugin will be able to most reliably invalidate the cache for a URL via
 * this ID if the relevant actions are triggered for the post (e.g. clean_post_cache, save_post, transition_post_status).
 *
 * Otherwise, if the response is an archive page or the front page where show_on_front=posts (i.e. is_home), then
 * there is no singular post object that represents the URL. In this case, we obtain the first post in the main
 * loop. By triggering the relevant actions for this post ID, page caches will have their best shot at invalidating
 * the related URLs. Page caching plugins which leverage surrogate keys will be the most reliable here. Otherwise,
 * caching plugins may just resort to automatically purging the cache for the homepage whenever any post is edited,
 * which is better than nothing.
 *
 * There should not be any situation by default in which a page optimized with Optimization Detective does not have such
 * a post available for cache purging. As seen in {@see od_can_optimize_response()}, when such a post ID is not
 * available for cache purging then it returns false, as it also does in another case like if is_404().
 *
 * @since 0.8.0
 * @access private
 *
 * @global WP_Query $wp_query WordPress Query object.
 *
 * @return int|null Post ID or null if none found.
 */
function od_get_cache_purge_post_id(): ?int {
	$queried_object = get_queried_object();
	if ( $queried_object instanceof WP_Post ) {
		return $queried_object->ID;
	}

	global $wp_query;
	if (
		$wp_query instanceof WP_Query
		&&
		$wp_query->post_count > 0
		&&
		isset( $wp_query->posts[0] )
		&&
		$wp_query->posts[0] instanceof WP_Post
	) {
		return $wp_query->posts[0]->ID;
	}

	return null;
}

/**
 * Prints the script for detecting loaded images and the LCP element.
 *
 * @since 0.1.0
 * @access private
 *
 * @param string                         $slug             URL Metrics slug.
 * @param OD_URL_Metric_Group_Collection $group_collection URL Metric group collection.
 */
function od_get_detection_script( string $slug, OD_URL_Metric_Group_Collection $group_collection ): string {

	/**
	 * Filters whether to use the web-vitals.js build with attribution.
	 *
	 * When using the attribution build of web-vitals, the metric object passed to report callbacks registered via
	 * `onTTFB`, `onFCP`, `onLCP`, `onCLS`, and `onINP` will include an additional {@link https://github.com/GoogleChrome/web-vitals#attribution attribution property}.
	 * For details, please refer to the {@link https://github.com/GoogleChrome/web-vitals web-vitals documentation}.
	 *
	 * For example, to opt in to using the attribution build:
	 *
	 *     add_filter( 'od_use_web_vitals_attribution_build', '__return_true' );
	 *
	 * Note that the attribution build is slightly larger than the standard build, so this is why it is not used by default.
	 * The additional attribution data is made available to client-side extension script modules registered via the `od_extension_module_urls` filter.
	 *
	 * @since n.e.x.t
	 *
	 * @param bool $use_attribution_build Whether to use the attribution build.
	 */
	$use_attribution_build = (bool) apply_filters( 'od_use_web_vitals_attribution_build', false );

	$web_vitals_lib_data = require __DIR__ . '/build/web-vitals.asset.php';
	$web_vitals_lib_src  = $use_attribution_build ?
		plugins_url( 'build/web-vitals-attribution.js', __FILE__ ) :
		plugins_url( 'build/web-vitals.js', __FILE__ );
	$web_vitals_lib_src  = add_query_arg( 'ver', $web_vitals_lib_data['version'], $web_vitals_lib_src );

	/**
	 * Filters the list of extension script module URLs to import when performing detection.
	 *
	 * @since 0.7.0
	 *
	 * @param string[] $extension_module_urls Extension module URLs.
	 */
	$extension_module_urls = (array) apply_filters( 'od_extension_module_urls', array() );

	$cache_purge_post_id = od_get_cache_purge_post_id();

	$current_url = od_get_current_url();

	$current_etag = $group_collection->get_current_etag();

	$detect_args = array(
		'minViewportAspectRatio' => od_get_minimum_viewport_aspect_ratio(),
		'maxViewportAspectRatio' => od_get_maximum_viewport_aspect_ratio(),
		'isDebug'                => WP_DEBUG,
		'extensionModuleUrls'    => $extension_module_urls,
		'restApiEndpoint'        => rest_url( OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE ),
		'currentETag'            => $current_etag,
		'currentUrl'             => $current_url,
		'urlMetricSlug'          => $slug,
		'cachePurgePostId'       => od_get_cache_purge_post_id(),
		'urlMetricHMAC'          => od_get_url_metrics_storage_hmac( $slug, $current_etag, $current_url, $cache_purge_post_id ),
		'urlMetricGroupStatuses' => array_map(
			static function ( OD_URL_Metric_Group $group ): array {
				return array(
					'minimumViewportWidth' => $group->get_minimum_viewport_width(),
					'complete'             => $group->is_complete(),
				);
			},
			iterator_to_array( $group_collection )
		),
		'storageLockTTL'         => OD_Storage_Lock::get_ttl(),
		'webVitalsLibrarySrc'    => $web_vitals_lib_src,
	);
	if ( WP_DEBUG ) {
		$detect_args['urlMetricGroupCollection'] = $group_collection;
	}

	return wp_get_inline_script_tag(
		sprintf(
			'import detect from %s; detect( %s );',
			wp_json_encode( plugins_url( add_query_arg( 'ver', OPTIMIZATION_DETECTIVE_VERSION, od_get_asset_path( 'detect.js' ) ), __FILE__ ) ),
			wp_json_encode( $detect_args )
		),
		array( 'type' => 'module' )
	);
}
