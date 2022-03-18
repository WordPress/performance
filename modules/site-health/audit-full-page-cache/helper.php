<?php
/**
 * Helper functions used by module.
 *
 * @package performance-lab
 * @since 1.0.0
 */


function get_good_response_time_threshold() {
	/**
	 * Filters the threshold below which a response time is considered good.
	 *
	 * @since 2.2.1
	 * @param int $threshold Threshold in milliseconds.
	 */
	return (int) apply_filters( 'amp_page_cache_good_response_time_threshold', 600 );
}

function get_page_cache_headers() {

	$cache_hit_callback = static function ( $header_value ) {
		return false !== strpos( strtolower( $header_value ), 'hit' );
	};

	return [
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
	];
}

function check_for_page_caching() {

	/** This filter is documented in wp-includes/class-wp-http-streams.php */
	$sslverify = apply_filters( 'https_local_ssl_verify', false );

	$headers = [];

	// Include basic auth in loopback requests. Note that this will only pass along basic auth when user is
	// initiating the test. If a site requires basic auth, the test will fail when it runs in WP Cron as part of
	// wp_site_health_scheduled_check. This logic is copied from WP_Site_Health::can_perform_loopback() in core.
	if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.BasicAuthentication
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.ServerVariables.BasicAuthentication
		$headers['Authorization'] = 'Basic ' . base64_encode( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) . ':' . wp_unslash( $_SERVER['PHP_AUTH_PW'] ) );
	}

	$caching_headers               = get_page_cache_headers();
	$page_caching_response_headers = [];
	$response_timing               = [];

	//add_filter( 'home_url', function(){ return 'https://abf8-139-47-117-177.ngrok.io/';} );

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

		$response_headers = [];

		foreach ( $caching_headers as $header => $callback ) {
			$header_value = wp_remote_retrieve_header( $http_response, $header );
			if (
				$header_value
				&&
				(
					empty( $callback )
					||
					( is_callable( $callback ) && true === $callback( $header_value ) )
				)
			) {
				$response_headers[ $header ] = $header_value;
			}
		}

		$page_caching_response_headers[] = $response_headers;
		$response_timing[]               = ( $end_time - $start_time ) * 1000;
	}

	return [
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
	];
}

function get_page_cache_detail( $use_previous_result = false ) {

	if ( $use_previous_result ) {
		$page_cache_detail = get_transient( 'perflab_has_page_caching' );
	}

	if ( ! $use_previous_result || empty( $page_cache_detail ) ) {
		$page_cache_detail = check_for_page_caching();
		if ( is_wp_error( $page_cache_detail ) ) {
			set_transient( 'perflab_has_page_caching', $page_cache_detail, DAY_IN_SECONDS );
		} else {
			set_transient( 'perflab_has_page_caching', $page_cache_detail, MONTH_IN_SECONDS );
		}
	}

	if ( is_wp_error( $page_cache_detail ) ) {
		return $page_cache_detail;
	}

	// Use the median server response time.
	$response_timings = $page_cache_detail['response_timing'];
	rsort( $response_timings );
	$page_speed = $response_timings[ floor( count( $response_timings ) / 2 ) ];

	// Obtain unique set of all client caching response headers.
	$headers = [];
	foreach ( $page_cache_detail['page_caching_response_headers'] as $page_caching_response_headers ) {
		$headers = array_merge( $headers, array_keys( $page_caching_response_headers ) );
	}
	$headers = array_unique( $headers );

	// Page caching is detected if there are response headers or a page caching plugin is present.
	$has_page_caching = ( count( $headers ) > 0 || $page_cache_detail['advanced_cache_present'] );

	if ( $page_speed && $page_speed < get_good_response_time_threshold() ) {
		$result = $has_page_caching ? 'good' : 'recommended';
	} else {
		$result = 'critical';
	}

	return [
		'status'                 => $result,
		'advanced_cache_present' => $page_cache_detail['advanced_cache_present'],
		'headers'                => $headers,
		'response_time'          => $page_speed,
	];
}
