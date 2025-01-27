<?php
/**
 * REST API integration for the plugin.
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
 * Namespace for optimization-detective.
 *
 * @var string
 */
const OD_REST_API_NAMESPACE = 'optimization-detective/v1';

/**
 * Route for storing a URL Metric.
 *
 * Note the `:store` art of the endpoint follows Google's guidance in AIP-136 for the use of the POST method in a way
 * that does not strictly follow the standard usage. Namely, submitting a POST request to this endpoint will either
 * create a new `od_url_metrics` post, or it will update an existing post if one already exists for the provided slug.
 *
 * @link https://google.aip.dev/136
 * @var string
 */
const OD_URL_METRICS_ROUTE = '/url-metrics:store';

/**
 * Route for getting URLs that need to be primed.
 *
 * @var string
 */
const OD_PRIME_URLS_ROUTE = '/prime-urls';

/**
 * Registers endpoint for storage of URL Metric.
 *
 * @since 0.1.0
 * @access private
 *
 * @see od_compose_site_health_result()
 */
function od_register_endpoint(): void {

	// The slug and cache_purge_post_id args are further validated via the validate_callback for the 'hmac' parameter,
	// they are provided as input with the 'url' argument to create the HMAC by the server.
	$args = array(
		'slug'                => array(
			'type'        => 'string',
			'description' => __( 'An MD5 hash of the query args.', 'optimization-detective' ),
			'required'    => true,
			'pattern'     => '^[0-9a-f]{32}\z',
			'minLength'   => 32,
			'maxLength'   => 32,
		),
		'current_etag'        => array(
			'type'        => 'string',
			'description' => __( 'ETag for the current environment.', 'optimization-detective' ),
			'required'    => true,
			'pattern'     => '^[0-9a-f]{32}\z',
			'minLength'   => 32,
			'maxLength'   => 32,
		),
		'cache_purge_post_id' => array(
			'type'        => 'integer',
			'description' => __( 'Cache purge post ID.', 'optimization-detective' ),
			'required'    => false,
			'minimum'     => 1,
		),
		'hmac'                => array(
			'type'              => 'string',
			'description'       => __( 'HMAC originally computed by server required to authorize the request.', 'optimization-detective' ),
			'required'          => true,
			'pattern'           => '^[0-9a-f]+\z',
			'validate_callback' => static function ( string $hmac, WP_REST_Request $request ) {
				if ( ! od_verify_url_metrics_storage_hmac( $hmac, $request['slug'], $request['current_etag'], $request['url'], $request['cache_purge_post_id'] ?? null ) ) {
					return new WP_Error( 'invalid_hmac', __( 'URL Metrics HMAC verification failure.', 'optimization-detective' ) );
				}
				return true;
			},
		),
	);

	register_rest_route(
		OD_REST_API_NAMESPACE,
		OD_URL_METRICS_ROUTE,
		array(
			'methods'             => 'POST',
			'args'                => array_merge(
				$args,
				rest_get_endpoint_args_for_schema( OD_Strict_URL_Metric::get_json_schema() )
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				return od_handle_rest_request( $request );
			},
			'permission_callback' => static function () {
				// Needs to be available to unauthenticated visitors.
				if ( OD_Storage_Lock::is_locked() ) {
					return new WP_Error(
						'url_metric_storage_locked',
						__( 'URL Metric storage is presently locked for the current IP.', 'optimization-detective' ),
						array( 'status' => 403 ) // TODO: Consider 423 Locked status code.
					);
				}
				return true;
			},
		)
	);

	register_rest_route(
		OD_REST_API_NAMESPACE,
		OD_PRIME_URLS_ROUTE,
		array(
			'methods'             => 'POST',
			'callback'            => static function ( WP_REST_Request $request ) {
				return od_handle_generate_batch_urls_request( $request );
			},
			'permission_callback' => static function () {
				return current_user_can( 'manage_options' );
			},
		)
	);
}
add_action( 'rest_api_init', 'od_register_endpoint' );

/**
 * Determines if the HTTP origin is an authorized one.
 *
 * Note that `is_allowed_http_origin()` is not used directly because the underlying `get_allowed_http_origins()` does
 * not account for the URL port (although there is a to-do comment committed in core to address this). Additionally,
 * the `is_allowed_http_origin()` function in core for some reason returns a string rather than a boolean.
 *
 * @since 0.8.0
 * @access private
 *
 * @see is_allowed_http_origin()
 *
 * @param string $origin Origin to check.
 * @return bool Whether the origin is allowed.
 */
function od_is_allowed_http_origin( string $origin ): bool {
	// Strip out the port number since core does not account for it yet as noted in get_allowed_http_origins().
	$origin = preg_replace( '/:\d+$/', '', $origin );
	return '' !== is_allowed_http_origin( $origin );
}

/**
 * Handles REST API request to store metrics.
 *
 * @since 0.1.0
 * @access private
 *
 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function od_handle_rest_request( WP_REST_Request $request ) {
	// Block cross-origin storage requests since by definition URL Metrics data can only be sourced from the frontend of the site.
	$origin = $request->get_header( 'origin' );
	if ( null === $origin || ! od_is_allowed_http_origin( $origin ) ) {
		return new WP_Error(
			'rest_cross_origin_forbidden',
			__( 'Cross-origin requests are not allowed for this endpoint.', 'optimization-detective' ),
			array( 'status' => 403 )
		);
	}

	$post = OD_URL_Metrics_Post_Type::get_post( $request->get_param( 'slug' ) );

	$url_metric_group_collection = new OD_URL_Metric_Group_Collection(
		$post instanceof WP_Post ? OD_URL_Metrics_Post_Type::get_url_metrics_from_post( $post ) : array(),
		$request->get_param( 'current_etag' ),
		od_get_breakpoint_max_widths(),
		od_get_url_metrics_breakpoint_sample_size(),
		od_get_url_metric_freshness_ttl()
	);

	// Block the request if URL Metrics aren't needed for the provided viewport width.
	try {
		$url_metric_group = $url_metric_group_collection->get_group_for_viewport_width(
			$request->get_param( 'viewport' )['width']
		);
	} catch ( InvalidArgumentException $exception ) {
		// Note: This should never happen because an exception only occurs if a viewport width is less than zero, and the JSON Schema enforces that the viewport.width have a minimum of zero.
		return new WP_Error( 'invalid_viewport_width', $exception->getMessage() ); // @codeCoverageIgnore
	}
	if ( $url_metric_group->is_complete() ) {
		return new WP_Error(
			'url_metric_group_complete',
			__( 'The URL Metric group for the provided viewport is already complete.', 'optimization-detective' ),
			array( 'status' => 403 )
		);
	}

	$data = $request->get_json_params();
	if ( ! is_array( $data ) ) {
		return new WP_Error(
			'missing_array_json_body',
			__( 'The request body is not JSON array.', 'optimization-detective' ),
			array( 'status' => 400 )
		);
	}

	OD_Storage_Lock::set_lock();

	try {
		// The "strict" URL Metric class is being used here to ensure additionalProperties of all objects are disallowed.
		$url_metric = new OD_Strict_URL_Metric(
			array_merge(
				$data,
				array(
					// Now supply the readonly args which were omitted from the REST API params due to being `readonly`.
					'timestamp' => microtime( true ),
					'uuid'      => wp_generate_uuid4(),
					'etag'      => $request->get_param( 'current_etag' ),
				)
			)
		);
	} catch ( OD_Data_Validation_Exception $e ) {
		return new WP_Error(
			'rest_invalid_param',
			sprintf(
				/* translators: %s is exception message */
				__( 'Failed to validate URL Metric: %s', 'optimization-detective' ),
				$e->getMessage()
			),
			array( 'status' => 400 )
		);
	}

	// TODO: This should be changed from store_url_metric($slug, $url_metric) instead be update_post( $slug, $group_collection ). As it stands, store_url_metric() is duplicating logic here.
	$result = OD_URL_Metrics_Post_Type::store_url_metric(
		$request->get_param( 'slug' ),
		$url_metric
	);
	if ( $result instanceof WP_Error ) {
		$error_data = array(
			'status' => 500,
		);
		if ( WP_DEBUG ) {
			$error_data['error_code']    = $result->get_error_code();
			$error_data['error_message'] = $result->get_error_message();
		}
		return new WP_Error(
			'unable_to_store_url_metric',
			__( 'Unable to store URL Metric.', 'optimization-detective' ),
			$error_data
		);
	}
	$post_id = $result;

	// Schedule an event in 10 minutes to trigger an invalidation of the page cache (hopefully).
	$cache_purge_post_id = $request->get_param( 'cache_purge_post_id' );
	if ( is_int( $cache_purge_post_id ) && false === wp_next_scheduled( 'od_trigger_page_cache_invalidation', array( $cache_purge_post_id ) ) ) {
		wp_schedule_single_event(
			time() + 10 * MINUTE_IN_SECONDS,
			'od_trigger_page_cache_invalidation',
			array( $cache_purge_post_id )
		);
	}

	/**
	 * Fires whenever a URL Metric was successfully stored.
	 *
	 * @since 0.7.0
	 *
	 * @param OD_URL_Metric_Store_Request_Context $context Context about the successful URL Metric collection.
	 */
	do_action(
		'od_url_metric_stored',
		new OD_URL_Metric_Store_Request_Context(
			$request,
			$post_id,
			$url_metric_group_collection,
			$url_metric_group,
			$url_metric
		)
	);

	return new WP_REST_Response(
		array(
			'success' => true,
		)
	);
}

/**
 * Handles REST API request to generate batch URLs.
 *
 * @since n.e.x.t
 * @access private
 *
 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response Response.
 */
function od_handle_generate_batch_urls_request( WP_REST_Request $request ): WP_REST_Response {
	$cursor = $request->get_param( 'cursor' );

	// Initialize cursor with default values.
	$cursor = wp_parse_args(
		$cursor,
		array(
			'provider_index'     => 0,
			'subtype_index'      => 0,
			'page_number'        => 1,
			'offset_within_page' => 0,
			'batch_size'         => 1,
		)
	);

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

	return new WP_REST_Response(
		array(
			'urls'   => $all_urls,
			'cursor' => $new_cursor,
		)
	);
}

/**
 * Triggers actions for page caches to invalidate their caches related to the supplied cache purge post ID.
 *
 * This is intended to flush any page cache for the URL after the new URL Metric was submitted so that the optimizations
 * which depend on that URL Metric can start to take effect.
 *
 * @since 0.8.0
 * @access private
 *
 * @param positive-int $cache_purge_post_id Cache purge post ID.
 */
function od_trigger_page_cache_invalidation( int $cache_purge_post_id ): void {
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
