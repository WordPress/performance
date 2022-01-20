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
		__( 'https://wordpress.org/support/article/optimization/#object-caching', 'performance-lab' )
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
	if ( ! apply_filters( 'site_status_persistent_object_cache', false ) ) {
		$result['label'] = __( 'A persistent object cache is not required', 'performance-lab' );

		return $result;
	}

	$available_services = oc_health_available_persistent_object_cache_services();

	$description = sprintf(
		/* translators: Available object caching services. */
		__( 'Your host appears to support the following object caching services: %s. Speak to your web host about what persistent object caches are available and how to enable them.', 'performance-lab' ),
		implode( ', ', $available_services )
	);

	$result['status']       = 'recommended';
	$result['label']        = __( 'You should use a persistent object cache', 'performance-lab' );
	$result['description'] .= sprintf( '<p>%s</p>', $description );

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
	global $wpdb;

	$thresholds = array(
		'alloptions_count' => 1,
		'alloptions_bytes' => 100000,
		'comments_count'   => 1000,
		'options_count'    => 1000,
		'posts_count'      => 1000,
		'terms_count'      => 1000,
		'users_count'      => 1000,
	);

	$alloptions = wp_load_alloptions();

	if ( $thresholds['alloptions_count'] < count( $alloptions ) ) {
		return true;
	}

	if ( $thresholds['alloptions_bytes'] < strlen( serialize( wp_load_alloptions() ) ) ) {
		return true;
	}

	$table_names = implode( "','", array( $wpdb->comments, $wpdb->options, $wpdb->posts, $wpdb->terms, $wpdb->users ) );

	// With InnoDB the `TABLE_ROWS` are estimates, which are accurate enough and faster to retrieve than individual `COUNT()` queries.
	$results = $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT TABLE_NAME AS 'table', TABLE_ROWS AS 'rows', SUM(data_length + index_length) as 'bytes'
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = %s
			AND TABLE_NAME IN ('$table_names')
			GROUP BY TABLE_NAME;",
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			DB_NAME
		),
		OBJECT_K
	);

	if ( $thresholds['comments_count'] < $results[ $wpdb->comments ]->rows ) {
		return true;
	}

	if ( $thresholds['options_count'] < $results[ $wpdb->options ]->rows ) {
		return true;
	}

	if ( $thresholds['posts_count'] < $results[ $wpdb->posts ]->rows ) {
		return true;
	}

	if ( $thresholds['terms_count'] < $results[ $wpdb->terms ]->rows ) {
		return true;
	}

	if ( $thresholds['users_count'] < $results[ $wpdb->users ]->rows ) {
		return true;
	}

	return false;
}

/**
 * Returns a list of available persistent object cache services.
 *
 * @return array The list of available persistent object cache services.
 */
function oc_health_available_persistent_object_cache_services() {
	$extensions = array_map(
		'extension_loaded',
		array(
			'Redis'     => 'redis',
			'Relay'     => 'relay',
			'Memcached' => 'memcache', // The `memcached` extension seems unmaintained.
			'APCu'      => 'apcu',
		)
	);

	$services = array_keys( array_filter( $extensions ) );

	/**
	 * Filter the persistent object cache services available to the user.
	 *
	 * @param array $suggest
	 */
	return apply_filters( 'site_status_available_object_cache_services', $services );
}
