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

const ILO_PAGE_METRIC_STORAGE_ROUTE = '/image-loading-optimization/page-metric-storage';

/**
 * Register endpoint for storage of page metric.
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
		ILO_PAGE_METRIC_STORAGE_ROUTE,
		array(
			'methods'             => 'POST',
			'callback'            => 'ilo_handle_rest_request',
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
 * Handle REST API request to store metrics.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function ilo_handle_rest_request( WP_REST_Request $request ) {
	ilo_set_page_metric_storage_lock();

	$page_metric = $request->get_json_params();
	$result      = ilo_store_page_metric( $page_metric );

	if ( $result instanceof WP_Error ) {
		return $result;
	}

	$response = new WP_REST_Response(
		array(
			'success' => true,
			'post_id' => $result,
		)
	);
	$response->set_status( 201 );
	return $response;
}
