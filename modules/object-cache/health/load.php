<?php
/**
 * Module Name: Health Check
 * Description: Recommend a persistent object cache for sites with non-trivial amounts of data.
 * Experimental: No
 *
 * @package performance-lab
 * @since 1.0.0
 */

add_filter( 'site_status_tests', 'oc_health_add_tests' );
add_filter( 'site_status_persistent_object_cache', 'oc_health_should_persistent_object_cache' );

/**
 * Adds a health check testing for and suggesting a persistent object cache backend.
 *
 * @param array $tests An associative array of direct and asynchronous tests.
 * @return array
 */
function oc_health_add_tests( $tests ) {
	$tests['direct']['persistent_object_cache'] = array(
		'label' => 'persistent_object_cache',
		'test'  => 'oc_health_persistent_object_cache',
	);

	return $tests;
}

/**
 * Callback for `persistent_object_cache` health check.
 *
 * @return array
 */
function oc_health_persistent_object_cache() {
	/**
	 * Filter the action URL for the persistent object cache health check.
	 *
	 * @param string $action_url Learn more link for persistent object cache health check.
	 */
	$action_url = apply_filters(
		'site_status_persistent_object_cache_url',
		/* translators: Localized Support reference. */
		__( 'https://wordpress.org/support/article/object-cache/', 'performance-lab' )
	);

	$result = array(
		'test'        => 'persistent_object_cache',
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			__( "WordPress performs at its best when a persistent object cache is used. Persistent object caching helps to reduce load on your SQL server and allows WordPress to retrieve your site's content and settings much faster.", 'performance-lab' )
		),
		'actions'     => sprintf(
			'<p><a href="%s" target="_blank" rel="noopener">%s <span class="screen-reader-text">%s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a></p>',
			esc_url( $action_url ),
			__( 'Learn more about persistent object caching.', 'performance-lab' ),
			/* translators: Accessibility text. */
			__( '(opens in a new tab)', 'performance-lab' )
		),
	);

	if ( wp_using_ext_object_cache() ) {
		$result['label'] = __( 'A persistent object cache is being used', 'performance-lab' );

		return $result;
	}

	/**
	 * Filter whether to suggest using a persistent object cache.
	 *
	 * @param bool $suggest
	 */
	if ( apply_filters( 'site_status_persistent_object_cache', false ) ) {
		$result['status']       = 'recommended';
		$result['label']        = __( 'You should use a persistent object cache', 'performance-lab' );
		$result['description'] .= sprintf(
			'<p>%s</p>',
			__( '...', 'performance-lab' )
		);

		return $result;
	}

	$result['label'] = __( 'A persistent object cache is not required', 'performance-lab' );

	return $result;
}

/**
 * Callback for `site_status_persistent_object_cache` filter.
 *
 * Determines whether to suggest using a persistent object cache.
 *
 * @param mixed $should_suggest Whether to suggest using a persistent object cache.
 * @return bool
 */
function oc_health_should_persistent_object_cache( $should_suggest ) {
	return true;
}
