<?php
/**
 * Site Health checks.
 *
 * @package optimization-detective
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Adds the Optimization Detective REST API check to site health tests.
 *
 * @since 1.0.0
 * @access private
 *
 * @param array{direct: array<string, array{label: string, test: string}>}|mixed $tests Site Health Tests.
 * @return array{direct: array<string, array{label: string, test: string}>} Amended tests.
 */
function od_add_rest_api_availability_test( $tests ): array {
	if ( ! is_array( $tests ) ) {
		$tests = array();
	}
	$tests['direct']['optimization_detective_rest_api'] = array(
		'label' => __( 'Optimization Detective REST API Endpoint Availability', 'optimization-detective' ),
		'test'  => static function () {
			// Note: A closure is used here to improve symbol discovery for the sake of potential refactoring.
			return od_test_rest_api_availability();
		},
	);

	return $tests;
}

/**
 * Tests availability of the Optimization Detective REST API endpoint.
 *
 * @since 1.0.0
 * @access private
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} Result.
 */
function od_test_rest_api_availability(): array {
	$response       = od_get_rest_api_health_check_response( false );
	$result         = od_compose_site_health_result( $response );
	$is_unavailable = 'good' !== $result['status'];
	update_option(
		'od_rest_api_unavailable',
		$is_unavailable ? '1' : '0',
		true // Intentionally autoloaded since used on every frontend request.
	);
	return $result;
}

/**
 * Checks whether the Optimization Detective REST API endpoint is unavailable.
 *
 * This merely checks the database option what was previously computed in the Site Health test as done in {@see od_test_rest_api_availability()}.
 * This is to avoid checking for REST API availability during a frontend request. Note that when the plugin is first
 * installed, the 'od_rest_api_unavailable' option will not be in the database, as the check has not been performed
 * yet. Once Site Health's weekly check happens or when a user accesses the admin so that the admin_init action fires,
 * then at this point the check will be performed at {@see od_maybe_run_rest_api_health_check()}. In practice, this will
 * happen immediately after the user activates a plugin since the user is redirected back to the plugin list table in
 * the admin. The reason for storing the negative unavailable state as opposed to the positive available state is that
 * when an option does not exist then `get_option()` returns `false` which is the same falsy value as the stored `'0'`.
 *
 * @since 1.0.0
 * @access private
 *
 * @return bool Whether unavailable.
 */
function od_is_rest_api_unavailable(): bool {
	return 1 === (int) get_option( 'od_rest_api_unavailable', '0' );
}

/**
 * Tests availability of the Optimization Detective REST API endpoint.
 *
 * @since 1.0.0
 * @access private
 *
 * @param array<string, mixed>|WP_Error $response REST API response.
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string} Result.
 */
function od_compose_site_health_result( $response ): array {
	$common_description_html = '<p>' . wp_kses(
		sprintf(
			/* translators: %s is the REST API endpoint */
			__( 'To collect URL Metrics from visitors the REST API must be available to unauthenticated users. Specifically, visitors must be able to perform a <code>POST</code> request to the <code>%s</code> endpoint.', 'optimization-detective' ),
			'/' . OD_REST_URL_Metrics_Store_Endpoint::ROUTE_NAMESPACE . OD_REST_URL_Metrics_Store_Endpoint::ROUTE_BASE
		),
		array( 'code' => array() )
	) . '</p>';

	$result = array(
		'label'       => __( 'The Optimization Detective REST API endpoint is available', 'optimization-detective' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Optimization Detective', 'optimization-detective' ),
			'color' => 'blue',
		),
		'description' => $common_description_html . '<p><strong>' . esc_html__( 'This appears to be working properly.', 'optimization-detective' ) . '</strong></p>',
		'actions'     => '',
		'test'        => 'optimization_detective_rest_api',
	);

	$error_label            = __( 'The Optimization Detective REST API endpoint is unavailable', 'optimization-detective' );
	$error_description_html = '<p>' . esc_html__( 'You may have a plugin active or server configuration which restricts access to logged-in users. Unauthenticated access must be restored in order for Optimization Detective to work.', 'optimization-detective' ) . '</p>';

	if ( is_wp_error( $response ) ) {
		$result['status']      = 'recommended';
		$result['label']       = $error_label;
		$result['description'] = $common_description_html . $error_description_html . '<p>' . wp_kses(
			sprintf(
				/* translators: %s is the error code */
				__( 'The REST API responded with the error code <code>%s</code> and the following error message:', 'optimization-detective' ),
				esc_html( (string) $response->get_error_code() )
			),
			array( 'code' => array() )
		) . '</p><blockquote>' . esc_html( $response->get_error_message() ) . '</blockquote>';
	} else {
		$code    = wp_remote_retrieve_response_code( $response );
		$message = wp_remote_retrieve_response_message( $response );
		$body    = wp_remote_retrieve_body( $response );
		$data    = json_decode( $body, true );
		$header  = wp_remote_retrieve_header( $response, 'content-type' );
		if ( is_array( $header ) ) {
			$header = array_pop( $header );
		}

		$is_expected = (
			400 === $code &&
			isset( $data['code'], $data['data']['params'] ) &&
			'rest_missing_callback_param' === $data['code'] &&
			is_array( $data['data']['params'] ) &&
			count( $data['data']['params'] ) > 0
		);
		if ( ! $is_expected ) {
			$result['status'] = 'recommended';
			if ( 401 === $code ) {
				$result['label'] = __( 'The Optimization Detective REST API endpoint is unavailable to logged-out users', 'optimization-detective' );
			} else {
				$result['label'] = $error_label;
			}
			$result['description'] = $common_description_html . $error_description_html . '<p>' . wp_kses(
				sprintf(
					/* translators: %d is the HTTP status code, %s is the status header description */
					__( 'The REST API returned with an HTTP status of <code>%1$d %2$s</code>.', 'optimization-detective' ),
					$code,
					esc_html( $message )
				),
				array( 'code' => array() )
			) . '</p>';

			if ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
				$result['description'] .= '<blockquote>' . esc_html( $data['message'] ) . '</blockquote>';
			}

			if ( '' !== $body ) {
				$result['description'] .= '<details>';
				$result['description'] .= '<summary>' . esc_html__( 'Raw response:', 'optimization-detective' ) . '</summary>';

				if ( is_string( $header ) && str_contains( $header, 'html' ) ) {
					$escaped_content        = htmlspecialchars( $body, ENT_QUOTES, 'UTF-8' );
					$result['description'] .= '<iframe srcdoc="' . $escaped_content . '" sandbox width="100%" height="300"></iframe>';
				} else {
					$result['description'] .= '<pre style="white-space: pre-wrap">' . esc_html( $body ) . '</pre>';
				}
				$result['description'] .= '</details>';
			}
		}
	}
	return $result;
}

/**
 * Gets the response to an Optimization Detective REST API store request to confirm it is available to unauthenticated requests.
 *
 * @since 1.0.0
 * @access private
 *
 * @param bool $use_cached Whether to use a previous response cached in a transient.
 * @return array{ response: array{ code: int, message: string }, body: string }|WP_Error Response.
 */
function od_get_rest_api_health_check_response( bool $use_cached ) {
	$transient_key = 'od_rest_api_health_check_response';
	$response      = $use_cached ? get_transient( $transient_key ) : false;
	if ( false !== $response ) {
		return $response;
	}
	$rest_url = get_rest_url( null, OD_REST_URL_Metrics_Store_Endpoint::ROUTE_NAMESPACE . OD_REST_URL_Metrics_Store_Endpoint::ROUTE_BASE );
	$response = wp_remote_post(
		$rest_url,
		array(
			'headers'   => array( 'Content-Type' => 'application/json' ),
			'sslverify' => false,
		)
	);

	// This transient will be used when showing the admin notice with the plugin on the plugins screen.
	// The 1-day expiration allows for fresher content than the weekly check initiated by Site Health.
	set_transient( $transient_key, $response, DAY_IN_SECONDS );
	return $response;
}

/**
 * Renders an admin notice if the REST API health check fails.
 *
 * @since 1.0.0
 * @access private
 *
 * @param bool $in_plugin_row Whether the notice is to be printed in the plugin row.
 */
function od_maybe_render_rest_api_health_check_admin_notice( bool $in_plugin_row ): void {
	if ( ! od_is_rest_api_unavailable() ) {
		return;
	}

	$response = od_get_rest_api_health_check_response( true );
	$result   = od_compose_site_health_result( $response );
	if ( 'good' === $result['status'] ) {
		// There's a slight chance the DB option is stale in the initial if statement.
		return;
	}

	$message = sprintf(
		$in_plugin_row
			? '<summary style="margin: 0.5em 0">%s %s</summary>'
			: '<p><strong>%s %s</strong></p>',
		esc_html__( 'Warning:', 'optimization-detective' ),
		esc_html( $result['label'] )
	);

	$message .= $result['description']; // This has already gone through Kses.

	if ( current_user_can( 'view_site_health_checks' ) ) {
		$site_health_message = wp_kses(
			sprintf(
				/* translators: %s is the URL to the Site Health admin screen */
				__( 'Please visit <a href="%s">Site Health</a> to re-check this once you believe you have resolved the issue.', 'optimization-detective' ),
				esc_url( admin_url( 'site-health.php' ) )
			),
			array( 'a' => array( 'href' => array() ) )
		);
		$message .= "<p><em>$site_health_message</em></p>";
	}

	if ( $in_plugin_row ) {
		$message = "<details>$message</details>";
	}

	$notice = wp_get_admin_notice(
		$message,
		array(
			'type'               => 'warning',
			'additional_classes' => $in_plugin_row ? array( 'inline', 'notice-alt' ) : array(),
			'paragraph_wrap'     => false,
		)
	);

	echo wp_kses(
		$notice,
		array_merge(
			wp_kses_allowed_html( 'post' ),
			array(
				'iframe' => array_fill_keys( array( 'srcdoc', 'sandbox', 'width', 'height' ), true ),
			)
		)
	);
}

/**
 * Displays an admin notice on the plugin row if the REST API health check fails.
 *
 * @since 1.0.0
 * @access private
 *
 * @param non-empty-string $plugin_file Plugin file.
 */
function od_render_rest_api_health_check_admin_notice_in_plugin_row( string $plugin_file ): void {
	if ( 'optimization-detective/load.php' !== $plugin_file ) { // TODO: What if a different plugin slug is used?
		return;
	}
	od_maybe_render_rest_api_health_check_admin_notice( true );
}

/**
 * Runs the REST API health check if it hasn't been run yet.
 *
 * This happens at the `admin_init` action to avoid running the check on the frontend. This will run on the first admin
 * page load after the plugin has been activated. This allows for this function to add an action at `admin_notices` so
 * that an error message can be displayed after performing that plugin activation request. Note that a plugin activation
 * hook cannot be used for this purpose due to not being compatible with multisite. While the site health notice is
 * shown at the `admin_notices` action once, the notice will only be displayed inline with the plugin row thereafter
 * via {@see od_render_rest_api_health_check_admin_notice_in_plugin_row()}.
 *
 * @since 1.0.0
 * @access private
 */
function od_maybe_run_rest_api_health_check(): void {
	// If the option already exists, then the REST API health check has already been performed.
	if ( false !== get_option( 'od_rest_api_unavailable' ) ) {
		return;
	}

	// This will populate the od_rest_api_unavailable option so that the function won't execute on the next page load.
	if ( 'good' !== od_test_rest_api_availability()['status'] ) {
		// Show any notice in the main admin notices area for the first page load (e.g. after plugin activation).
		add_action(
			'admin_notices',
			static function (): void {
				od_maybe_render_rest_api_health_check_admin_notice( false );
			}
		);
	}
}
