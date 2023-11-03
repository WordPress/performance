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
 * Register endpoint for storage of metrics.
 */
function image_loading_optimization_register_endpoint() {

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
		'perflab/v1',
		'/image-loading-optimization/metrics-storage',
		array(
			'methods'             => 'POST',
			'callback'            => 'image_loading_optimization_handle_rest_request',
			'permission_callback' => '__return_true', // Needs to be available to unauthenticated visitors.
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
add_action( 'rest_api_init', 'image_loading_optimization_register_endpoint' );

/**
 * Handle REST API request to store metrics.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response Response.
 */
function image_loading_optimization_handle_rest_request( WP_REST_Request $request ) {

	return new WP_REST_Response(
		array(
			'success' => true,
			'body'    => $request->get_json_params(),
		)
	);
}
