<?php
/**
 * REST API integration for the module.
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Namespace for optimization-detective.
 *
 * @var string
 */
const OD_REST_API_NAMESPACE = 'optimization-detective/v1';

/**
 * Route for storing a URL metric.
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
 * Registers endpoint for storage of URL metric.
 *
 * @since n.e.x.t
 * @access private
 */
function od_register_endpoint() {

	$args = array(
		'slug'  => array(
			'type'        => 'string',
			'description' => __( 'An MD5 hash of the query args.', 'optimization-detective' ),
			'required'    => true,
			'pattern'     => '^[0-9a-f]{32}$',
			// This is further validated via the validate_callback for the nonce argument, as it is provided as input
			// with the 'url' argument to create the nonce by the server. which then is verified to match in the REST API request.
		),
		'nonce' => array(
			'type'              => 'string',
			'description'       => __( 'Nonce originally computed by server required to authorize the request.', 'optimization-detective' ),
			'required'          => true,
			'pattern'           => '^[0-9a-f]+$',
			'validate_callback' => static function ( $nonce, WP_REST_Request $request ) {
				if ( ! od_verify_url_metrics_storage_nonce( $nonce, $request->get_param( 'slug' ), $request->get_param( 'url' ) ) ) {
					return new WP_Error( 'invalid_nonce', __( 'URL metrics nonce verification failure.', 'optimization-detective' ) );
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
				rest_get_endpoint_args_for_schema( OD_URL_Metric::get_json_schema() )
			),
			'callback'            => static function ( WP_REST_Request $request ) {
				return od_handle_rest_request( $request );
			},
			'permission_callback' => static function () {
				// Needs to be available to unauthenticated visitors.
				if ( OD_Storage_Lock::is_locked() ) {
					return new WP_Error(
						'url_metric_storage_locked',
						__( 'URL metric storage is presently locked for the current IP.', 'optimization-detective' ),
						array( 'status' => 403 )
					);
				}
				return true;
			},
		)
	);
}
add_action( 'rest_api_init', 'od_register_endpoint' );

/**
 * Handles REST API request to store metrics.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function od_handle_rest_request( WP_REST_Request $request ) {
	$post = od_get_url_metrics_post( $request->get_param( 'slug' ) );

	$group_collection = new OD_URL_Metrics_Group_Collection(
		$post ? od_parse_stored_url_metrics( $post ) : array(),
		od_get_breakpoint_max_widths(),
		od_get_url_metrics_breakpoint_sample_size(),
		od_get_url_metric_freshness_ttl()
	);

	// Block the request if URL metrics aren't needed for the provided viewport width.
	try {
		$group = $group_collection->get_group_for_viewport_width(
			$request->get_param( 'viewport' )['width']
		);
	} catch ( InvalidArgumentException $exception ) {
		return new WP_Error( 'invalid_viewport_width', $exception->getMessage() );
	}
	if ( $group->is_complete() ) {
		return new WP_Error(
			'url_metrics_group_complete',
			__( 'The URL metrics group for the provided viewport is already complete.', 'optimization-detective' ),
			array( 'status' => 403 )
		);
	}

	OD_Storage_Lock::set_lock();

	try {
		$properties = OD_URL_Metric::get_json_schema()['properties'];
		$url_metric = new OD_URL_Metric(
			array_merge(
				wp_array_slice_assoc(
					$request->get_params(),
					array_keys( $properties )
				),
				array(
					// Now supply the timestamp since it was omitted from the REST API params since it is `readonly`.
					// Nevertheless, it is also `required`, so it must be set to instantiate an OD_URL_Metric.
					'timestamp' => $properties['timestamp']['default'],
				)
			)
		);
	} catch ( OD_Data_Validation_Exception $e ) {
		return new WP_Error(
			'url_metric_exception',
			sprintf(
				/* translators: %s is exception name */
				__( 'Failed to validate URL metric: %s', 'optimization-detective' ),
				$e->getMessage()
			)
		);
	}

	$result = od_store_url_metric(
		$request->get_param( 'slug' ),
		$url_metric
	);

	if ( $result instanceof WP_Error ) {
		return $result;
	}

	return new WP_REST_Response(
		array(
			'success' => true,
		)
	);
}
