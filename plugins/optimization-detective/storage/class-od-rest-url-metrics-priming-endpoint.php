<?php
/**
 * REST API integration for the plugin: OD_REST_URL_Metrics_Priming_Endpoint.
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
 * OD_REST_URL_Metrics_Priming_Endpoint class
 *
 * @since n.e.x.t
 */
final class OD_REST_URL_Metrics_Priming_Endpoint {

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
	const PRIME_URLS_BREAKPOINTS_ROUTE = '/prime-urls-breakpoints';

	/**
	 * Route for verifying the token for auto priming URLs.
	 *
	 * @var string
	 */
	const PRIME_URLS_VERIFICATION_TOKEN_ROUTE = '/prime-urls-verification-token';

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

		$default_cursor = array(
			'provider_index'     => 0,
			'subtype_index'      => 0,
			'page_number'        => 1,
			'offset_within_page' => 0,
			'batch_size'         => 10,
		);

		// Initialize cursor with default values.
		$cursor = wp_parse_args( $cursor, $default_cursor );

		if ( $default_cursor === $cursor ) {
			$last_cursor = get_option( 'od_prime_url_metrics_batch_cursor' );
			if ( false !== $last_cursor ) {
				$cursor = wp_parse_args( $last_cursor, $cursor );
			}
		} else {
			update_option( 'od_prime_url_metrics_batch_cursor', $cursor );
		}

		$batch               = array();
		$filtered_batch_urls = array();
		$prevent_infinite    = 0;
		while ( $prevent_infinite < 100 ) {
			if ( count( $filtered_batch_urls ) > 0 ) {
				break;
			}

			$batch               = od_get_batch_for_iframe_url_metrics_priming( $cursor );
			$filtered_batch_urls = od_filter_batch_urls_for_iframe_url_metrics_priming( $batch['urls'] );

			if ( $cursor === $batch['cursor'] ) {
				delete_option( 'od_prime_url_metrics_batch_cursor' );
				break;
			}
			$cursor = $batch['cursor'];

			++$prevent_infinite;
		}

		$verification_token = get_transient( 'od_prime_url_metrics_verification_token' );

		if ( false === $verification_token ) {
			$verification_token = wp_generate_uuid4();
			set_transient( 'od_prime_url_metrics_verification_token', $verification_token, 30 * MINUTE_IN_SECONDS );
		}

		return new WP_REST_Response(
			array(
				'batch'             => $filtered_batch_urls,
				'cursor'            => $batch['cursor'],
				'verificationToken' => $verification_token,
				'isDebug'           => defined( 'WP_DEBUG' ) && WP_DEBUG,
			)
		);
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
		$verification_token = get_transient( 'od_prime_url_metrics_verification_token' );
		if ( false === $verification_token ) {
			$verification_token = wp_generate_uuid4();
			set_transient( 'od_prime_url_metrics_verification_token', $verification_token, 30 * MINUTE_IN_SECONDS );
		}
		return new WP_REST_Response( $verification_token );
	}
}
