<?php
/**
 * Helper functions used by module.
 *
 * @package performance-lab
 * @since 1.2.0
 */

/**
 * Returns a list of headers and its verification callback to verify if page cache is enabled or not.
 *
 * Note: key is header name and value could be callable function to verify header value.
 * Empty value mean existence of header detect page cache is enabled.
 *
 * @since 1.2.0
 *
 * @return array List of client caching headers and their (optional) verification callbacks.
 */
function perflab_afpc_get_page_cache_headers() {

	$cache_hit_callback = static function ( $header_value ) {
		return false !== strpos( strtolower( $header_value ), 'hit' );
	};

	return array(
		'cache-control'          => static function ( $header_value ) {
			return (bool) preg_match( '/max-age=[1-9]/', $header_value );
		},
		'expires'                => static function ( $header_value ) {
			return strtotime( $header_value ) > time();
		},
		'age'                    => static function ( $header_value ) {
			return is_numeric( $header_value ) && $header_value > 0;
		},
		'last-modified'          => '',
		'etag'                   => '',
		'x-cache'                => $cache_hit_callback,
		'x-proxy-cache'          => $cache_hit_callback,
		'cf-cache-status'        => $cache_hit_callback,
		'x-kinsta-cache'         => $cache_hit_callback,
		'x-aruba-cache'          => $cache_hit_callback,
		'x-cache-enabled'        => static function ( $header_value ) {
			return 'true' === strtolower( $header_value );
		},
		'x-cache-disabled'       => static function ( $header_value ) {
			return ( 'on' !== strtolower( $header_value ) );
		},
		'cf-apo-via'             => static function ( $header_value ) {
			return false !== strpos( strtolower( $header_value ), 'tcache' );
		},
		'x-srcache-store-status' => $cache_hit_callback,
		'x-srcache-fetch-status' => $cache_hit_callback,
		'cf-edge-cache'          => static function ( $header_value ) {
			return false !== strpos( strtolower( $header_value ), 'cache' );
		},
	);
}

/**
 * Checks if site has page cache enabled or not.
 *
 * @since 1.2.0
 *
 * @return WP_Error|array {
 *     Page caching detection details or else error information.
 *
 *     @type bool    $advanced_cache_present        Whether a page caching plugin is present.
 *     @type array[] $page_caching_response_headers Sets of client caching headers for the responses.
 *     @type float[] $response_timing               Response timings.
 * }
 */
function perflab_afpc_check_for_page_caching() {

	/** This filter is documented in wp-includes/class-wp-http-streams.php */
	$sslverify = apply_filters( 'https_local_ssl_verify', false );

	$headers = array();

	// Include basic auth in loopback requests. Note that this will only pass along basic auth when user is
	// initiating the test. If a site requires basic auth, the test will fail when it runs in WP Cron as part of
	// wp_site_health_scheduled_check. This logic is copied from WP_Site_Health::can_perform_loopback() in core.
	if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.BasicAuthentication
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.ServerVariables.BasicAuthentication
		$headers['Authorization'] = 'Basic ' . base64_encode( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) . ':' . wp_unslash( $_SERVER['PHP_AUTH_PW'] ) );
	}

	$caching_headers               = perflab_afpc_get_page_cache_headers();
	$page_caching_response_headers = array();
	$response_timing               = array();

	for ( $i = 1; $i <= 3; $i++ ) {
		$start_time    = microtime( true );
		$http_response = wp_remote_get( home_url( '/' ), compact( 'sslverify', 'headers' ) );
		$end_time      = microtime( true );

		if ( is_wp_error( $http_response ) ) {
			return $http_response;
		}
		if ( wp_remote_retrieve_response_code( $http_response ) !== 200 ) {
			return new WP_Error(
				'http_' . wp_remote_retrieve_response_code( $http_response ),
				wp_remote_retrieve_response_message( $http_response )
			);
		}

		$response_headers = array();

		foreach ( $caching_headers as $header => $callback ) {
			$header_values = wp_remote_retrieve_header( $http_response, $header );
			if ( empty( $header_values ) ) {
				continue;
			}
			$header_values = (array) $header_values;
			if (
				empty( $callback )
				||
				(
					is_callable( $callback )
					&&
					count( array_filter( $header_values, $callback ) ) > 0
				)
			) {
				$response_headers[ $header ] = $header_values;
			}
		}

		$page_caching_response_headers[] = $response_headers;
		$response_timing[]               = ( $end_time - $start_time ) * 1000;
	}

	return array(
		'advanced_cache_present'        => (
			file_exists( WP_CONTENT_DIR . '/advanced-cache.php' )
			&&
			( defined( 'WP_CACHE' ) && WP_CACHE )
			&&
			/** This filter is documented in wp-settings.php */
			apply_filters( 'enable_loading_advanced_cache_dropin', true )
		),
		'page_caching_response_headers' => $page_caching_response_headers,
		'response_timing'               => $response_timing,
	);
}

/**
 * Get page cache details.
 *
 * @since 1.2.0
 *
 * @return WP_Error|array {
 *    Page cache detail or else a WP_Error if unable to determine.
 *
 *    @type string   $status                 Page cache status. Good, Recommended or Critical.
 *    @type bool     $advanced_cache_present Whether page cache plugin is available or not.
 *    @type string[] $headers                Client caching response headers detected.
 *    @type float    $response_time          Response time of site.
 * }
 */
function perflab_afpc_get_page_cache_detail() {
	$page_cache_detail = perflab_afpc_check_for_page_caching();
	if ( is_wp_error( $page_cache_detail ) ) {
		return $page_cache_detail;
	}

	// Use the median server response time.
	$response_timings = $page_cache_detail['response_timing'];
	rsort( $response_timings );
	$page_speed = $response_timings[ floor( count( $response_timings ) / 2 ) ];

	// Obtain unique set of all client caching response headers.
	$headers = array();
	foreach ( $page_cache_detail['page_caching_response_headers'] as $page_caching_response_headers ) {
		$headers = array_merge( $headers, array_keys( $page_caching_response_headers ) );
	}
	$headers = array_unique( $headers );

	// Page caching is detected if there are response headers or a page caching plugin is present.
	$has_page_caching = ( count( $headers ) > 0 || $page_cache_detail['advanced_cache_present'] );

	if ( $page_speed && $page_speed < perflab_afpc_get_good_response_time_threshold() ) {
		$result = $has_page_caching ? 'good' : 'recommended';
	} else {
		$result = 'critical';
	}

	return array(
		'status'                 => $result,
		'advanced_cache_present' => $page_cache_detail['advanced_cache_present'],
		'headers'                => $headers,
		'response_time'          => $page_speed,
	);
}

/**
 * Get the threshold below which a response time is considered good.
 *
 * @since 1.2.0
 *
 * @return int Threshold in milliseconds.
 */
function perflab_afpc_get_good_response_time_threshold() {
	/**
	 * Filters the threshold below which a response time is considered good.
	 *
	 * @since 1.2.0
	 * @param int $threshold Threshold in milliseconds. Default 600.
	 */
	return (int) apply_filters( 'perflab_afpc_page_cache_good_response_time_threshold', 600 );
}
