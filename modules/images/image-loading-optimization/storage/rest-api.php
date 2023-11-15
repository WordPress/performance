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

const ILO_REST_API_NAMESPACE = 'image-loading-optimization/v1';

const ILO_PAGE_METRICS_ROUTE = '/page-metrics';

/**
 * Registers endpoint for storage of page metric.
 */
function ilo_register_endpoint() /*: void (in PHP 7.1) */ {

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
		ILO_PAGE_METRICS_ROUTE,
		array(
			'methods'             => 'POST',
			'callback'            => static function ( WP_REST_Request $request ) {
				return ilo_handle_rest_request( $request );
			},
			'permission_callback' => static function () {
				// Needs to be available to unauthenticated visitors.
				if ( ilo_is_page_metric_storage_locked() ) {
					return new WP_Error(
						'page_metric_storage_locked',
						__( 'Page metric storage is presently locked for the current IP.', 'performance-lab' ),
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
						if ( ! ilo_verify_page_metrics_storage_nonce( $nonce, $request->get_param( 'slug' ) ) ) {
							return new WP_Error( 'invalid_nonce', __( 'Page metrics nonce verification failure.', 'performance-lab' ) );
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
							'breadcrumbs'        => array(
								'type'     => 'array',
								'required' => true,
								'items'    => array(
									'type'       => 'object',
									'properties' => array(
										'tagName' => array(
											'type'     => 'string',
											'required' => true,
											'pattern'  => '^[a-zA-Z0-9-]+$',
										),
										'index'   => array(
											'type'     => 'int',
											'required' => true,
											'minimum'  => 0,
										),
									),
								),
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
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function ilo_handle_rest_request( WP_REST_Request $request ) /*: WP_REST_Response|WP_Error (in PHP 8) */ {
	$needed_minimum_viewport_widths = ilo_get_needed_minimum_viewport_widths_now_for_slug( $request->get_param( 'slug' ) );
	if ( ! ilo_needs_page_metric_for_breakpoint( $needed_minimum_viewport_widths ) ) {
		return new WP_Error(
			'no_page_metric_needed',
			__( 'No page metric needed for any of the breakpoints.', 'performance-lab' ),
			array( 'status' => 403 )
		);
	}

	ilo_set_page_metric_storage_lock();
	$new_page_metric = wp_array_slice_assoc( $request->get_json_params(), array( 'viewport', 'elements' ) );

	$result = ilo_store_page_metric(
		$request->get_param( 'url' ),
		$request->get_param( 'slug' ),
		$new_page_metric
	);

	if ( $result instanceof WP_Error ) {
		return $result;
	}

	return new WP_REST_Response(
		array(
			'success' => true,
			'post_id' => $result,
			'data'    => ilo_parse_stored_page_metrics( ilo_get_page_metrics_post( $request->get_param( 'slug' ) ) ), // TODO: Remove this debug data.
		)
	);
}
