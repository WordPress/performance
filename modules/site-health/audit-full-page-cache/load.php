<?php
/**
 * Module Name: Full Page Cache Health Check
 * Description: Adds a check for full page cache in Site Health status.
 * Experimental: No
 *
 * @package performance-lab
 * @since 1.2.0
 */

/**
 * Require helper functions.
 */
require_once __DIR__ . '/helper.php';

/**
 * Adds tests to site health.
 *
 * @since 1.2.0
 *
 * @param array $tests Site Health Tests.
 * @return array Modified Site Health tests including Page caching test.
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
 * @since 1.2.0
 *
 * @return array The test results.
 */
function perflab_afpc_page_cache_test() {
	$description  = '<p>' . __( 'Page caching enhances the speed and performance of your site by saving and serving static pages instead of calling for a page every time a user visits.', 'performance-lab' ) . '</p>';
	$description .= '<p>' . __( 'Page caching is detected by looking for an active page caching plugin as well as making three requests to the homepage and looking for one or more of the following HTTP client caching response headers:', 'performance-lab' ) . '</p>';
	$description .= '<code>' . implode( '</code>, <code>', array_keys( perflab_afpc_get_page_cache_headers() ) ) . '.</code>';

	$result = array(
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'blue',
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
		$result['label']  = __( 'Unable to detect the presence of page caching', 'performance-lab' );
		$result['status'] = 'recommended';
		$error_info       = sprintf(
			/* translators: 1 is error message, 2 is error code */
			__( 'Unable to detect page caching due to possible loopback request problem. Please verify that the loopback request test is passing. Error: %1$s (Code: %2$s)', 'performance-lab' ),
			$page_cache_detail->get_error_message(),
			$page_cache_detail->get_error_code()
		);
		$result['description'] = wp_kses_post( "<p>$error_info</p>" ) . $result['description'];
		return $result;
	}

	$result['status'] = $page_cache_detail['status'];

	switch ( $page_cache_detail['status'] ) {
		case 'recommended':
			$result['label'] = __( 'Page caching is not detected but the server response time is OK', 'performance-lab' );
			break;
		case 'good':
			$result['label'] = __( 'Page caching is detected and the server response time is good', 'performance-lab' );
			break;
		default:
			if ( empty( $page_cache_detail['headers'] ) && ! $page_cache_detail['advanced_cache_present'] ) {
				$result['label'] = __( 'Page caching is not detected and the server response time is slow', 'performance-lab' );
			} else {
				$result['label'] = __( 'Page caching is detected but the server response time is still slow', 'performance-lab' );
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
				__( 'Median server response time was %1$s milliseconds. This is less than the recommended %2$s millisecond threshold.', 'performance-lab' ),
				number_format_i18n( $page_cache_detail['response_time'] ),
				number_format_i18n( $threshold )
			);
		} else {
			$page_cache_test_summary[] = '<span class="dashicons dashicons-warning"></span> ' . sprintf(
				/* translators: %d is the response time in milliseconds */
				__( 'Median server response time was %1$s milliseconds. It should be less than the recommended %2$s milliseconds threshold.', 'performance-lab' ),
				number_format_i18n( $page_cache_detail['response_time'] ),
				number_format_i18n( $threshold )
			);
		}

		if ( empty( $page_cache_detail['headers'] ) ) {
			$page_cache_test_summary[] = '<span class="dashicons dashicons-warning"></span> ' . __( 'No client caching response headers were detected.', 'performance-lab' );
		} else {
			$headers_summary  = '<span class="dashicons dashicons-yes-alt"></span>';
			$headers_summary .= sprintf(
				/* translators: Placeholder is number of caching headers */
				_n(
					' There was %d client caching response header detected: ',
					' There were %d client caching response headers detected: ',
					count( $page_cache_detail['headers'] ),
					'performance-lab'
				),
				count( $page_cache_detail['headers'] )
			);
			$headers_summary          .= '<code>' . implode( '</code>, <code>', $page_cache_detail['headers'] ) . '</code>.';
			$page_cache_test_summary[] = $headers_summary;
		}
	}

	if ( $page_cache_detail['advanced_cache_present'] ) {
		$page_cache_test_summary[] = '<span class="dashicons dashicons-yes-alt"></span> ' . __( 'A page caching plugin was detected.', 'performance-lab' );
	} elseif ( ! ( is_array( $page_cache_detail ) && ! empty( $page_cache_detail['headers'] ) ) ) {
		// Note: This message is not shown if client caching response headers were present since an external caching layer may be employed.
		$page_cache_test_summary[] = '<span class="dashicons dashicons-warning"></span> ' . __( 'A page caching plugin was not detected.', 'performance-lab' );
	}

	$result['description'] .= '<ul><li>' . implode( '</li><li>', $page_cache_test_summary ) . '</li></ul>';
	return $result;
}

/**
 * Register async test REST endpoint.
 *
 * @since 1.2.0
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
