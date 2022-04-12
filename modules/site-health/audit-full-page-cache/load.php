<?php
/**
 * Module Name: Audit Full Page Cache
 * Description: Adds a Full page Cache checker in Site Health checks.
 * Experimental: No
 *
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Require helper functions.
 */
require_once __DIR__ . '/helper.php';

/**
 * Adds tests to site health.
 *
 * @since 1.0.0
 *
 * @param array $tests Site Health Tests.
 * @return array
 */
function perflab_afpc_add_full_page_cache_test( $tests ) {
	$tests['async']['perflab_page_cache'] = array(
		'label'             => __( 'Page caching', 'performance-lab' ),
		'test'              => rest_url( 'performance-lab/v1/tests/page-cache' ),
		'has_rest'          => true,
		'async_direct_test' => 'perflab_afpc_page_cache_test',
	);
	return $tests;
}
add_filter( 'site_status_tests', 'perflab_afpc_add_full_page_cache_test' );

/**
 * Callback for perflab_page_cache test.
 *
 * @since 1.0.0
 *
 * @return array
 */
function perflab_afpc_page_cache_test() {
	$description  = '<p>' . __( 'WordPress performs at its best when page caching is enabled. This is because the additional optimizations performed require additional server processing time, and page caching ensures that responses are served quickly.', 'performance-lab' ) . '</p>';
	$description .= '<p>' . __( 'Page caching is detected by looking for an active page caching plugin as well as making three requests to the homepage and looking for one or more of the following HTTP client caching response headers:', 'performance-lab' )
					. ' <code>' . implode( '</code>, <code>', array_keys( perflab_afpc_get_page_cache_headers() ) ) . '.</code>';

	$result = array(
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'green',
		),
		'description' => wp_kses_post( $description ),
		'test'        => 'perflab_page_cache',
		'status'      => 'good',
		'label'       => '',
		'actions'     => sprintf(
			'<p><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s <span class="screen-reader-text">%3$s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
			esc_url( 'https://wordpress.org/support/article/optimization/#Caching' ),
			__( 'Learn more about page caching', 'performance-lab' ),
			/* translators: The accessibility text. */
			__( '(opens in a new tab)', 'performance-lab' )
		),
	);

	$page_cache_detail = perflab_afpc_get_page_cache_detail();

	if ( is_wp_error( $page_cache_detail ) ) {
		return perflab_afpc_page_cache_unable_detect_cache_test( $result, $page_cache_detail );
	}

	return perflab_afpc_page_cache_detected_test( $result, $page_cache_detail );
}

/**
 * Response when unable to detect the presence of page caching.
 *
 * @param array    $default_response Default perflab_page_cache callback response.
 * @param WP_Error $page_cache_detail_error get_page_cache_detail() response.
 *
 * @return array
 * @since 1.0.0
 */
function perflab_afpc_page_cache_unable_detect_cache_test( $default_response, $page_cache_detail_error ) {
	$default_response['badge']['color'] = 'red';
	$default_response['label']          = __( 'Unable to detect the presence of page caching', 'performance-lab' );
	$default_response['status']         = 'critical';
	$error_info                         = sprintf(
	/* translators: 1 is error message, 2 is error code */
		__( 'Unable to detect page caching due to possible loopback request problem. Please verify that the loopback request test is passing. Error: %1$s (Code: %2$s)', 'performance-lab' ),
		$page_cache_detail_error->get_error_message(),
		$page_cache_detail_error->get_error_code()
	);
	$default_response['description'] = wp_kses_post( "<p>$error_info</p>" ) . $default_response['description'];
	return $default_response;
}

/**
 * Response when page cache detected.
 *
 * @param array $default_response Default perflab_page_cache callback response.
 * @param array $page_cache_detail get_page_cache_detail() response.
 *
 * @return array
 * @since 1.0.0
 */
function perflab_afpc_page_cache_detected_test( $default_response, $page_cache_detail ) {

	$default_response['status'] = $page_cache_detail['status'];

	switch ( $page_cache_detail['status'] ) {
		case 'recommended':
			$default_response['badge']['color'] = 'orange';
			$default_response['label']          = __( 'Page caching is not detected but the server response time is OK', 'performance-lab' );
			break;
		case 'good':
			$default_response['badge']['color'] = 'green';
			$default_response['label']          = __( 'Page caching is detected and the server response time is good', 'performance-lab' );
			break;
		default:
			$default_response['badge']['color'] = 'red';
			$default_response['label']          = __( 'Page caching is detected but the server response time is still slow', 'performance-lab' );
			if ( empty( $page_cache_detail['headers'] ) && ! $page_cache_detail['advanced_cache_present'] ) {
				$default_response['label'] = __( 'Page caching is not detected and the server response time is slow', 'performance-lab' );
			}
	}

	$page_cache_test_summary = array();

	if ( empty( $page_cache_detail['response_time'] ) ) {
		$page_cache_test_summary[] = '<span class="dashicons dashicons-dismiss"></span> ' . __( 'Server response time could not be determined. Verify that loopback requests are working.', 'performance-lab' );
	} else {

		$threshold = perflab_afpc_get_good_response_time_threshold();
		if ( $page_cache_detail['response_time'] < $threshold ) {
			$page_cache_test_summary[] = '<span class="dashicons dashicons-yes-alt"></span> ' . sprintf(
				/* translators: %d is the response time in milliseconds */
				__( 'Median server response time was %1$s milliseconds. This is less than the %2$s millisecond threshold.', 'performance-lab' ),
				number_format_i18n( $page_cache_detail['response_time'] ),
				number_format_i18n( $threshold )
			);
		} else {
			$page_cache_test_summary[] = '<span class="dashicons dashicons-warning"></span> ' . sprintf(
				/* translators: %d is the response time in milliseconds */
				__( 'Median server response time was %1$s milliseconds. It should be less than %2$s milliseconds.', 'performance-lab' ),
				number_format_i18n( $page_cache_detail['response_time'] ),
				number_format_i18n( $threshold )
			);
		}

		if ( empty( $page_cache_detail['headers'] ) ) {
			$page_cache_test_summary[] = '<span class="dashicons dashicons-warning"></span> ' . __( 'No client caching response headers were detected.', 'performance-lab' );
		} else {
			$page_cache_test_summary[] = '<span class="dashicons dashicons-yes-alt"></span> ' .
										sprintf(
										/* translators: Placeholder is number of caching headers */
											_n(
												'There was %d client caching response header detected:',
												'There were %d client caching response headers detected:',
												count( $page_cache_detail['headers'] ),
												'performance-lab'
											),
											count( $page_cache_detail['headers'] )
										) . ' <code>' . implode( '</code>, <code>', $page_cache_detail['headers'] ) . '</code>.';
		}
	}

	if ( $page_cache_detail['advanced_cache_present'] ) {
		$page_cache_test_summary[] = '<span class="dashicons dashicons-yes-alt"></span> ' . __( 'A page caching plugin was detected.', 'performance-lab' );
	} elseif ( ! ( is_array( $page_cache_detail ) && ! empty( $page_cache_detail['headers'] ) ) ) {
		// Note: This message is not shown if client caching response headers were present since an external caching layer may be employed.
		$page_cache_test_summary[] = '<span class="dashicons dashicons-warning"></span> ' . __( 'A page caching plugin was not detected.', 'performance-lab' );
	}

	$default_response['description'] .= '<ul><li>' . implode( '</li><li>', $page_cache_test_summary ) . '</li></ul>';
	return $default_response;
}

/**
 * Register async test REST endpoint.
 *
 * @since 1.0.0
 */
function perflab_afpc_register_async_test_endpoints() {
	register_rest_route(
		'performance-lab/v1',
		'/tests/page-cache',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'perflab_afpc_page_cache_test',
				'permission_callback' => static function() {
					return current_user_can( 'view_site_health_checks' );
				},
			),
		)
	);
}
add_action( 'rest_api_init', 'perflab_afpc_register_async_test_endpoints' );
