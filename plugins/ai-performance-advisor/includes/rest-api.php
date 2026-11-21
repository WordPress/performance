<?php
/**
 * REST API endpoint for running an analysis.
 *
 * @package ai-performance-advisor
 *
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the analysis REST route.
 *
 * @since 1.0.0
 */
function aipa_register_rest_routes(): void {
	register_rest_route(
		'ai-performance-advisor/v1',
		'/analyze',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'aipa_rest_analyze',
			'permission_callback' => 'aipa_rest_permission_check',
			'args'                => array(
				'refresh' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => __( 'Whether to bypass the cache and run a fresh analysis.', 'ai-performance-advisor' ),
				),
			),
		)
	);
}

/**
 * Permission callback for the analysis route.
 *
 * @since 1.0.0
 *
 * @return bool|WP_Error True if allowed, error otherwise.
 */
function aipa_rest_permission_check() {
	if ( ! current_user_can( 'view_site_health_checks' ) ) {
		return new WP_Error(
			'aipa_forbidden',
			__( 'Sorry, you are not allowed to run a performance analysis.', 'ai-performance-advisor' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
	return true;
}

/**
 * Handles the analysis request.
 *
 * @since 1.0.0
 *
 * @param WP_REST_Request $request The REST request.
 * @phpstan-param WP_REST_Request<array<string, mixed>> $request The REST request.
 * @return WP_REST_Response|WP_Error Response with recommendations, or an error.
 */
function aipa_rest_analyze( WP_REST_Request $request ) {
	$analyzer        = new AIPA_Analyzer();
	$recommendations = $analyzer->analyze( ! (bool) $request->get_param( 'refresh' ) );

	if ( is_wp_error( $recommendations ) ) {
		$recommendations->add_data( array( 'status' => 500 ), $recommendations->get_error_code() );
		return $recommendations;
	}

	return rest_ensure_response(
		array(
			'recommendations' => $recommendations,
		)
	);
}
