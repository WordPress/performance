<?php
/**
 * REST API integration for the plugin: OD_REST_URL_Metrics_Store_Endpoint.
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
 * OD_REST_URL_Metrics_Store_Endpoint class
 *
 * @since 1.0.0
 */
final class OD_REST_URL_Metrics_Store_Endpoint {

	/**
	 * Namespace for the REST API endpoint.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ROUTE_NAMESPACE = 'optimization-detective/v1';

	/**
	 * Route for storing a URL Metric.
	 *
	 * Note the `:store` art of the endpoint follows Google's guidance in AIP-136 for the use of the POST method in a way
	 * that does not strictly follow the standard usage. Namely, submitting a POST request to this endpoint will either
	 * create a new `od_url_metrics` post, or it will update an existing post if one already exists for the provided slug.
	 *
	 * @since 1.0.0
	 * @link https://google.aip.dev/136
	 * @var string
	 */
	const ROUTE_BASE = '/url-metrics:store';

	/**
	 * Gets the arguments for registering the endpoint.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @return array{
	 *     methods: string,
	 *     args: array<string, mixed>,
	 *     callback: callable,
	 *     permission_callback: callable
	 * }
	 */
	public function get_registration_args(): array {
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
					if ( '' === $hmac || ! od_verify_url_metrics_storage_hmac( $hmac, $request['slug'], $request['current_etag'], $request['url'], $request['cache_purge_post_id'] ?? null ) ) {
						return new WP_Error( 'invalid_hmac', __( 'URL Metrics HMAC verification failure.', 'optimization-detective' ) );
					}
					return true;
				},
			),
		);

		return array(
			'methods'             => 'POST',
			'args'                => array_merge(
				$args,
				rest_get_endpoint_args_for_schema( OD_Strict_URL_Metric::get_json_schema() )
			),
			'callback'            => array( $this, 'handle_rest_request' ),
			'permission_callback' => array( $this, 'store_permissions_check' ),
		);
	}

	/**
	 * Checks if a given request has access to store URL Metrics.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @return true|WP_Error True if the request has permission, WP_Error object otherwise.
	 */
	public function store_permissions_check() {

		// Needs to be available to unauthenticated visitors.
		if ( OD_Storage_Lock::is_locked() ) {
			return new WP_Error(
				'url_metric_storage_locked',
				__( 'URL Metric storage is presently locked for the current IP.', 'optimization-detective' ),
				array( 'status' => 423 )
			);
		}
		return true;
	}

	/**
	 * Determines if the HTTP origin is an authorized one.
	 *
	 * Note that `is_allowed_http_origin()` is not used directly because the underlying `get_allowed_http_origins()` does
	 * not account for the URL port (although there is a to-do comment committed in core to address this). Additionally,
	 * the `is_allowed_http_origin()` function in core for some reason returns a string rather than a boolean.
	 *
	 * @since 1.0.0
	 * @see is_allowed_http_origin()
	 * @access private
	 *
	 * @param string $origin Origin to check.
	 * @return bool Whether the origin is allowed.
	 */
	protected static function is_allowed_http_origin( string $origin ): bool {
		// Strip out the port number since core does not account for it yet as noted in get_allowed_http_origins().
		$origin = preg_replace( '/:\d+$/', '', $origin );
		return '' !== is_allowed_http_origin( $origin );
	}

	/**
	 * Handles the REST API request to store a URL Metric.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function handle_rest_request( WP_REST_Request $request ) {
		// Block cross-origin storage requests since by definition URL Metrics data can only be sourced from the frontend of the site.
		$origin = $request->get_header( 'origin' );
		if ( null === $origin || ! self::is_allowed_http_origin( $origin ) ) {
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
		} catch ( InvalidArgumentException $exception ) { // @codeCoverageIgnore
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

		/*
		 * The limit for data sent via navigator.sendBeacon() is 64 KiB. This limit is checked in detect.js so that the
		 * request will not even be attempted if the payload is too large. This server-side restriction is added as a
		 * safeguard against clients sending possibly malicious payloads much larger than 64 KiB which should never be
		 * getting sent.
		 */
		$max_size       = 64 * 1024; // 64 KB
		$content_length = strlen( (string) wp_json_encode( $url_metric ) );
		if ( $content_length > $max_size ) {
			return new WP_Error(
				'rest_content_too_large',
				sprintf(
					/* translators: 1: the size of the payload, 2: the maximum allowed payload size */
					__( 'JSON payload size is %1$s bytes which is larger than the maximum allowed size of %2$s bytes.', 'optimization-detective' ),
					number_format_i18n( $content_length ),
					number_format_i18n( $max_size )
				),
				array( 'status' => 413 )
			);
		}

		try {
			$url_metric_group->add_url_metric( $url_metric );
		} catch ( InvalidArgumentException $e ) { // @codeCoverageIgnore
			// NOTE: This exception should never be thrown because `get_group_for_viewport_width()` already ensures the viewport width is valid.
			return new WP_Error( 'invalid_url_metric', $e->getMessage() ); // @codeCoverageIgnore
		}

		$result = OD_URL_Metrics_Post_Type::update_post(
			$request->get_param( 'slug' ),
			$url_metric_group_collection
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
}
