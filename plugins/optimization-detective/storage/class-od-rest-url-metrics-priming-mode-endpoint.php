<?php
/**
 * REST API integration for the plugin: OD_REST_URL_Metrics_Priming_Mode_Endpoint.
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * OD_REST_URL_Metrics_Priming_Mode_Endpoint class
 *
 * @since n.e.x.t
 */
final class OD_REST_URL_Metrics_Priming_Mode_Endpoint {

	/**
	 * Route for getting URLs that need to be primed.
	 *
	 * @var string
	 */
	const PRIME_URLS_ROUTE = '/prime-urls';

	/**
	 * Route for getting breakpoints for URL Metrics.
	 *
	 * @var string
	 */
	const PRIME_URLS_BREAKPOINTS_ROUTE = '/priming-mode-breakpoints';

	/**
	 * Route for verifying the token for priming mode.
	 *
	 * @var string
	 */
	const PRIME_URLS_VERIFICATION_TOKEN_ROUTE = '/priming-mode-verification-token';

	/**
	 * Gets the arguments for registering the endpoint responsible for getting URLs that needs to be primed.
	 *
	 * @since n.e.x.t
	 * @access private
	 *
	 * @return array{
	 *     methods: string,
	 *     callback: callable,
	 *     permission_callback: callable
	 * }
	 */
	public function get_registration_args_prime_urls(): array {
		return array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_generate_batch_urls_request' ),
			'permission_callback' => array( $this, 'priming_permissions_check' ),
		);
	}

	/**
	 * Gets the arguments for registering the endpoint responsible for getting breakpoints for priming URL Metrics.
	 *
	 * @since n.e.x.t
	 * @access private
	 *
	 * @return array{
	 *     methods: string,
	 *     callback: callable,
	 *     permission_callback: callable
	 * }
	 */
	public function get_registration_args_prime_urls_breakpoints(): array {
		return array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_generate_breakpoints_request' ),
			'permission_callback' => array( $this, 'priming_permissions_check' ),
		);
	}

	/**
	 * Gets the arguments for registering the endpoint responsible for getting verification token for priming URLs Metrics.
	 *
	 * @since n.e.x.t
	 * @access private
	 *
	 * @return array{
	 *     methods: string,
	 *     callback: callable,
	 *     permission_callback: callable
	 * }
	 */
	public function get_registration_args_prime_urls_verification_token(): array {
		return array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_get_verification_token_request' ),
			'permission_callback' => array( $this, 'priming_permissions_check' ),
		);
	}

	/**
	 * Checks if a given request has access to prime URL metrics.
	 *
	 * @since n.e.x.t
	 * @access private
	 *
	 * @return true|WP_Error True if the request has permission, WP_Error object otherwise.
	 */
	public function priming_permissions_check() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to access this resource.', 'optimization-detective' ),
			array( 'status' => rest_authorization_required_code() )
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
	public function handle_generate_batch_urls_request( WP_REST_Request $request ): WP_REST_Response {
		$cursor = $request->get_param( 'cursor' );
		return new WP_REST_Response( od_generate_priming_mode_batch( $cursor ) );
	}

	/**
	 * Handles REST API request to generate breakpoints for URL Metrics.
	 *
	 * @since n.e.x.t
	 * @access private
	 *
	 * @return WP_REST_Response Response.
	 */
	public function handle_generate_breakpoints_request(): WP_REST_Response {
		return new WP_REST_Response( od_get_standard_breakpoints() );
	}

	/**
	 * Handles REST API request to get verification token for priming URLs Metrics.
	 *
	 * @since n.e.x.t
	 * @access private
	 *
	 * @return WP_REST_Response Response.
	 */
	public function handle_get_verification_token_request(): WP_REST_Response {
		return new WP_REST_Response( od_get_priming_mode_verification_token() );
	}
}
