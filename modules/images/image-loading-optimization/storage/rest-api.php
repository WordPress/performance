<?php
/**
 * REST API integration for the module.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Namespace for image-loading-optimization.
 *
 * @var string
 */
const ILO_REST_API_NAMESPACE = 'image-loading-optimization/v1';

/**
 * Route for storing a URL metric.
 *
 * Note the `:store` art of the endpoint follows Google's guidance in AIP-136 for the use of the POST method in a way
 * that does not strictly follow the standard usage. Namely, submitting a POST request to this endpoint will either
 * create a new `ilo_url_metrics` post, or it will update an existing post if one already exists for the provided slug.
 *
 * @link https://google.aip.dev/136
 * @var string
 */
const ILO_URL_METRICS_ROUTE = '/url-metrics:store';

/**
 * Registers endpoint for storage of URL metric.
 *
 * @since n.e.x.t
 * @access private
 */
function ilo_register_endpoint() {

	$dom_rect_schema = array(
		'type'       => 'object',
		'properties' => array(
			'width'  => array(
				'type'    => 'number',
				'minimum' => 0,
			),
			'height' => array(
				'type'    => 'number',
				'minimum' => 0,
			),
			// TODO: There are other properties to define if we need them: x, y, top, right, bottom, left.
		),
	);

	register_rest_route(
		ILO_REST_API_NAMESPACE,
		ILO_URL_METRICS_ROUTE,
		array(
			'methods'             => 'POST',
			'callback'            => static function ( WP_REST_Request $request ) {
				return ilo_handle_rest_request( $request );
			},
			'permission_callback' => static function () {
				// Needs to be available to unauthenticated visitors.
				if ( ilo_is_url_metric_storage_locked() ) {
					return new WP_Error(
						'url_metric_storage_locked',
						__( 'URL metric storage is presently locked for the current IP.', 'performance-lab' ),
						array( 'status' => 403 )
					);
				}
				return true;
			},
			'args'                => array(
				'url'      => array(
					'type'              => 'string',
					'required'          => true,
					'format'            => 'uri',
					'validate_callback' => static function ( $url ) {
						if ( ! wp_validate_redirect( $url ) ) {
							return new WP_Error( 'non_origin_url', __( 'URL for another site provided.', 'performance-lab' ) );
						}
						return true;
					},
				),
				'slug'     => array(
					'type'     => 'string',
					'required' => true,
					'pattern'  => '^[0-9a-f]{32}$',
				),
				'nonce'    => array(
					'type'              => 'string',
					'required'          => true,
					'pattern'           => '^[0-9a-f]+$',
					'validate_callback' => static function ( $nonce, WP_REST_Request $request ) {
						if ( ! ilo_verify_url_metrics_storage_nonce( $nonce, $request->get_param( 'slug' ) ) ) {
							return new WP_Error( 'invalid_nonce', __( 'URL metrics nonce verification failure.', 'performance-lab' ) );
						}
						return true;
					},
				),
				'viewport' => array(
					'description' => __( 'Viewport dimensions', 'performance-lab' ),
					'type'        => 'object',
					'required'    => true,
					'properties'  => array(
						'width'  => array(
							'type'     => 'int',
							'required' => true,
							'minimum'  => 0,
						),
						'height' => array(
							'type'     => 'int',
							'required' => true,
							'minimum'  => 0,
						),
					),
				),
				'elements' => array(
					'description' => __( 'Element metrics', 'performance-lab' ),
					'type'        => 'array',
					'items'       => array(
						// See the ElementMetrics in detect.js.
						'type'       => 'object',
						'properties' => array(
							'isLCP'              => array(
								'type'     => 'bool',
								'required' => true,
							),
							'isLCPCandidate'     => array(
								'type' => 'bool',
							),
							'xpath'              => array(
								'type'     => 'string',
								'required' => true,
								'pattern'  => ILO_HTML_Tag_Processor::XPATH_PATTERN,
							),
							'intersectionRatio'  => array(
								'type'     => 'number',
								'required' => true,
								'minimum'  => 0.0,
								'maximum'  => 1.0,
							),
							'intersectionRect'   => $dom_rect_schema,
							'boundingClientRect' => $dom_rect_schema,
						),
					),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'ilo_register_endpoint' );

/**
 * Handles REST API request to store metrics.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function ilo_handle_rest_request( WP_REST_Request $request ) {
	$post = ilo_get_url_metrics_post( $request->get_param( 'slug' ) );

	$needed_minimum_viewport_widths = ilo_get_needed_minimum_viewport_widths(
		$post ? ilo_parse_stored_url_metrics( $post ) : array(),
		microtime( true ),
		ilo_get_breakpoint_max_widths(),
		ilo_get_url_metrics_breakpoint_sample_size(),
		ilo_get_url_metric_freshness_ttl()
	);
	if ( ! ilo_needs_url_metric_for_breakpoint( $needed_minimum_viewport_widths ) ) {
		return new WP_Error(
			'no_url_metric_needed',
			__( 'No URL metric needed for any of the breakpoints.', 'performance-lab' ),
			array( 'status' => 403 )
		);
	}

	ilo_set_url_metric_storage_lock();
	$new_url_metric = wp_array_slice_assoc( $request->get_json_params(), array( 'viewport', 'elements' ) );

	$result = ilo_store_url_metric(
		$request->get_param( 'url' ),
		$request->get_param( 'slug' ),
		$new_url_metric
	);

	if ( $result instanceof WP_Error ) {
		return $result;
	}

	return new WP_REST_Response(
		array(
			'success' => true,
			'post_id' => $result,
			'data'    => ilo_parse_stored_url_metrics( ilo_get_url_metrics_post( $request->get_param( 'slug' ) ) ), // TODO: Remove this debug data.
		)
	);
}
