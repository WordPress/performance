<?php
/**
 * Detection for Optimization Detective.
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
 * Gets the ID for a post related to this response so that page caches can be told to invalidate their cache.
 *
 * If the queried object for the response is a post, then that post's ID is used. Otherwise, it uses the ID of the first
 * post in The Loop.
 *
 * When the queried object is a post (e.g. is_singular, is_posts_page, is_front_page w/ show_on_front=page), then this
 * is the perfect match. A page caching plugin will be able to most reliably invalidate the cache for a URL via
 * this ID if the relevant actions are triggered for the post (e.g. clean_post_cache, save_post, transition_post_status).
 *
 * Otherwise, if the response is an archive page or the front page where show_on_front=posts (i.e. is_home), then
 * there is no singular post object that represents the URL. In this case, we get the first post in the main
 * loop. By triggering the relevant actions for this post ID, page caches will be more likely able to invalidate
 * the related URLs. Page caching plugins which leverage surrogate keys will be the most reliable here. Otherwise,
 * caching plugins may just resort to automatically purging the cache for the homepage whenever any post is edited,
 * which is better than nothing.
 *
 * There should not be any situation by default in which a page optimized with Optimization Detective does not have such
 * a post available for cache purging. As seen in {@see od_can_optimize_response()}, when such a post ID is not
 * available for cache purging, then it returns false, as it also does in another case like if is_404().
 *
 * @since 0.8.0
 * @access private
 *
 * @global WP_Query $wp_query WordPress Query object.
 *
 * @return positive-int|null Post ID or null if none found.
 */
function od_get_cache_purge_post_id(): ?int {
	$queried_object = get_queried_object();
	if ( $queried_object instanceof WP_Post && $queried_object->ID > 0 ) {
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
		&&
		$wp_query->posts[0]->ID > 0
	) {
		return $wp_query->posts[0]->ID;
	}

	return null;
}

/**
 * Prints the scripts for the detect loader.
 *
 * @since 0.1.0
 * @since 1.0.0 Renamed from od_get_detection_script().
 * @access private
 *
 * @param non-empty-string               $slug             URL Metrics slug.
 * @param OD_URL_Metric_Group_Collection $group_collection URL Metric group collection.
 */
function od_get_detection_scripts( string $slug, OD_URL_Metric_Group_Collection $group_collection ): string {

	/**
	 * Filters whether to use the web-vitals.js build with attribution.
	 *
	 * @since 1.0.0
	 * @link https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/hooks.md#:~:text=Filter%3A%20od_use_web_vitals_attribution_build
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
	 * @link https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/hooks.md#:~:text=Filter%3A%20od_extension_module_urls
	 *
	 * @param string[] $extension_module_urls Extension module URLs.
	 */
	$extension_module_urls = (array) apply_filters( 'od_extension_module_urls', array() );

	$cache_purge_post_id = od_get_cache_purge_post_id();
	$current_url         = od_get_current_url();
	$current_etag        = $group_collection->get_current_etag();

	/**
	 * Filters whether URL Metric JSON data should be compressed with gzip when being submitted to the `/url-metrics:store` REST API endpoint.
	 *
	 * @since 1.0.0
	 * @link https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/hooks.md#:~:text=Filter%3A%20od_gzip_url_metric_store_request_payloads
	 *
	 * @param bool $gzip_url_metric_store_request_payloads Whether to use gzip to compress URL Metric JSON.
	 */
	$gzdecode_available = function_exists( 'gzdecode' ) && apply_filters( 'od_gzip_url_metric_store_request_payloads', true );

	$detect_src = add_query_arg(
		array( 'ver' => OPTIMIZATION_DETECTIVE_VERSION ),
		plugins_url( od_get_asset_path( 'detect.js' ), __FILE__ )
	);

	$detect_args = array(
		'minViewportAspectRatio' => od_get_minimum_viewport_aspect_ratio(),
		'maxViewportAspectRatio' => od_get_maximum_viewport_aspect_ratio(),
		'isDebug'                => WP_DEBUG,
		'extensionModuleUrls'    => $extension_module_urls,
		'restApiEndpoint'        => rest_url( OD_REST_URL_Metrics_Store_Endpoint::ROUTE_NAMESPACE . OD_REST_URL_Metrics_Store_Endpoint::ROUTE_BASE ),
		'currentETag'            => $current_etag,
		'currentUrl'             => $current_url,
		'urlMetricSlug'          => $slug,
		'cachePurgePostId'       => od_get_cache_purge_post_id(),
		'urlMetricHMAC'          => od_get_url_metrics_storage_hmac( $slug, $current_etag, $current_url, $cache_purge_post_id ),
		'urlMetricGroupStatuses' => array_map(
			static function ( OD_URL_Metric_Group $group ): array {
				return array(
					'minimumViewportWidth' => $group->get_minimum_viewport_width(), // Exclusive.
					'maximumViewportWidth' => $group->get_maximum_viewport_width(), // Inclusive.
					'complete'             => $group->is_complete(),
				);
			},
			iterator_to_array( $group_collection )
		),
		'storageLockTTL'         => OD_Storage_Lock::get_ttl(),
		'freshnessTTL'           => od_get_url_metric_freshness_ttl(),
		'webVitalsLibrarySrc'    => $web_vitals_lib_src,
		'gzdecodeAvailable'      => $gzdecode_available,
		'maxUrlMetricSize'       => od_get_maximum_url_metric_size(),
	);
	if ( is_user_logged_in() ) {
		$detect_args['restApiNonce'] = wp_create_nonce( 'wp_rest' );
	}
	if ( WP_DEBUG ) {
		$detect_args['urlMetricGroupCollection'] = $group_collection;
	}

	$json_flags = JSON_HEX_TAG | JSON_UNESCAPED_SLASHES;
	if ( SCRIPT_DEBUG ) {
		$json_flags |= JSON_PRETTY_PRINT;
	}
	$json_script = wp_get_inline_script_tag(
		(string) wp_json_encode(
			array( $detect_src, $detect_args ),
			$json_flags
		),
		array(
			'type' => 'application/json',
			'id'   => 'optimization-detective-detect-args',
		)
	);

	$module_js  = file_get_contents( __DIR__ . '/' . od_get_asset_path( 'detect-loader.js' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.
	$module_js .= sprintf(
		"\n//# sourceURL=%s",
		add_query_arg(
			array( 'ver' => OPTIMIZATION_DETECTIVE_VERSION ),
			plugins_url( od_get_asset_path( 'detect-loader.js' ), __FILE__ )
		)
	);

	$module_script = wp_get_inline_script_tag(
		$module_js,
		array( 'type' => 'module' )
	);

	return $json_script . $module_script;
}

/**
 * Registers the REST API endpoint for storing URL Metrics.
 *
 * @since 1.0.0
 * @access private
 */
function od_register_rest_url_metric_store_endpoint(): void {
	$endpoint_controller = new OD_REST_URL_Metrics_Store_Endpoint();

	register_rest_route(
		$endpoint_controller::ROUTE_NAMESPACE,
		$endpoint_controller::ROUTE_BASE,
		$endpoint_controller->get_registration_args()
	);
}

/**
 * Decompresses the REST API request body for the URL Metrics endpoint.
 *
 * @since 1.0.0
 * @access private
 *
 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
 *
 * @param mixed           $result  Response to replace the requested version with. Can be anything a normal endpoint can return, or null to not hijack the request.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Request used to generate the response.
 * @return mixed Passed through $result if successful, or otherwise a WP_Error.
 */
function od_decompress_rest_request_body( $result, WP_REST_Server $server, WP_REST_Request $request ) {
	unset( $server ); // Unused.

	if (
		function_exists( 'gzdecode' ) &&
		$request->get_route() === '/' . OD_REST_URL_Metrics_Store_Endpoint::ROUTE_NAMESPACE . OD_REST_URL_Metrics_Store_Endpoint::ROUTE_BASE &&
		'gzip' === $request->get_header( 'Content-Encoding' )
	) {
		$compressed_body = $request->get_body();

		/*
		 * The limit for data sent via navigator.sendBeacon() is 64 KiB. This limit is checked in detect.js so that the
		 * request will not even be attempted if the payload is too large. This server-side restriction is added as a
		 * safeguard against clients sending possibly malicious payloads much larger than 64 KiB which should never be
		 * getting sent.
		 */
		$max_size       = 64 * 1024; // 64 KB
		$content_length = strlen( $compressed_body );
		if ( $content_length > $max_size ) {
			return new WP_Error(
				'rest_content_too_large',
				sprintf(
					/* translators: 1: the size of the payload, 2: the maximum allowed payload size */
					__( 'Compressed JSON payload size is %1$s bytes which is larger than the maximum allowed size of %2$s bytes.', 'optimization-detective' ),
					number_format_i18n( $content_length ),
					number_format_i18n( $max_size )
				),
				array( 'status' => 413 )
			);
		}

		$decompressed_body = @gzdecode( $compressed_body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- We need to suppress errors here.

		if ( false === $decompressed_body ) {
			return new WP_Error(
				'rest_invalid_payload',
				__( 'Unable to decompress the gzip payload.', 'optimization-detective' ),
				array( 'status' => 400 )
			);
		}

		// Update the request so later handlers see the decompressed JSON.
		$request->set_body( $decompressed_body );
		$request->remove_header( 'Content-Encoding' );
	}
	return $result;
}

/**
 * Triggers post update actions for page caches to invalidate their caches related to the supplied cache purge post ID.
 *
 * This is intended to flush any page cache for the URL after the new URL Metric was submitted so that the optimizations
 * which depend on that URL Metric can start to take effect.
 *
 * @since 1.0.0
 *
 * @param positive-int $cache_purge_post_id Cache purge post ID.
 */
function od_trigger_post_update_actions( int $cache_purge_post_id ): void {

	$post = get_post( $cache_purge_post_id );
	if ( ! ( $post instanceof WP_Post ) ) {
		return;
	}

	// Fire actions that page caching plugins listen to flush caches.

	/*
	* The clean_post_cache action is used to flush page caches by:
	* - Pantheon Advanced Cache <https://github.com/pantheon-systems/pantheon-advanced-page-cache/blob/e3b5552b0cb9268d9b696cb200af56cc044920d9/pantheon-advanced-page-cache.php#L185>
	* - WP Super Cache <https://github.com/Automattic/wp-super-cache/blob/73b428d2fce397fd874b3056ad3120c343bc1a0c/wp-cache-phase2.php#L1615>
	* - Batcache <https://github.com/Automattic/batcache/blob/ed0e6b2d9bcbab3924c49a6c3247646fb87a0957/batcache.php#L18>
	*/
	/** This action is documented in wp-includes/post.php. */
	do_action( 'clean_post_cache', $post->ID, $post );

	/*
	* The transition_post_status action is used to flush page caches by:
	* - Jetpack Boost <https://github.com/Automattic/jetpack-boost-production/blob/4090a3f9414c2171cd52d8a397f00b0d1151475f/app/modules/optimizations/page-cache/pre-wordpress/Boost_Cache.php#L76>
	* - WP Super Cache <https://github.com/Automattic/wp-super-cache/blob/73b428d2fce397fd874b3056ad3120c343bc1a0c/wp-cache-phase2.php#L1616>
	* - LightSpeed Cache <https://github.com/litespeedtech/lscache_wp/blob/7c707469b3c88b4f45d9955593b92f9aeaed54c3/src/purge.cls.php#L68>
	*/
	/** This action is documented in wp-includes/post.php. */
	do_action( 'transition_post_status', $post->post_status, $post->post_status, $post );

	/*
	* The clean_post_cache action is used to flush page caches by:
	* - W3 Total Cache <https://github.com/BoldGrid/w3-total-cache/blob/ab08f104294c6a8dcb00f1c66aaacd0615c42850/Util_AttachToActions.php#L32>
	* - WP Rocket <https://github.com/wp-media/wp-rocket/blob/e5bca6673a3669827f3998edebc0c785210fe561/inc/common/purge.php#L283>
	*/
	/** This action is documented in wp-includes/post.php. */
	do_action( 'save_post', $post->ID, $post, /* $update */ true );
}
