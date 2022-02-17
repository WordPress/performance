<?php
/**
 * Module Name: Persistent Object Cache Health Check
 * Description: Recommend a persistent object cache for sites with non-trivial amounts of data.
 * Experimental: No
 *
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Adds a health check testing for and suggesting a persistent object cache backend.
 *
 * @since 1.0.0
 *
 * @param array $tests An associative array of direct and asynchronous tests.
 * @return array An associative array of direct and asynchronous tests.
 */
function perflab_oc_health_add_tests( $tests ) {
	if ( wp_get_environment_type() === 'production' ) {
		$tests['direct']['persistent_object_cache'] = array(
			'label' => 'persistent_object_cache',
			'test'  => 'perflab_oc_health_persistent_object_cache',
		);
	}

	return $tests;
}
add_filter( 'site_status_tests', 'perflab_oc_health_add_tests' );

/**
 * Callback for `persistent_object_cache` health check.
 *
 * @since 1.0.0
 *
 * @return array The health check result suggesting persistent object caching.
 */
function perflab_oc_health_persistent_object_cache() {
	/**
	 * Filter the action URL for the persistent object cache health check.
	 *
	 * @since 1.0.0
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
			__( "WordPress performs at its best when a persistent object cache is used. A persistent object cache helps to reduce load on your SQL server significantly and allows WordPress to retrieve your site's content and settings much faster.", 'performance-lab' )
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
	 * Plugin and theme authors should NOT use this filter to discourage the use of an object cache.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $suggest Whether to suggest using a persistent object cache.
	 */
	if ( ! apply_filters( 'site_status_suggest_persistent_object_cache', false ) ) {
		$result['label'] = __( 'A persistent object cache is not required', 'performance-lab' );

		return $result;
	}

	$available_services = perflab_oc_health_available_object_cache_services();

	$notes = __( 'Speak to your web host about what persistent object caches are available and how to enable them.', 'performance-lab' );

	if ( ! empty( $available_services ) ) {
		$notes .= sprintf(
			/* translators: Available object caching services. */
			__( ' Your host appears to support the following object caching services: %s.', 'performance-lab' ),
			implode( ', ', $available_services )
		);
	}

	/**
	 * Filter the second paragraph of the health check's description
	 * when suggesting the use of a persistent object cache.
	 *
	 * Hosts may want to replace the notes to recommend their preferred object caching solution.
	 *
	 * Plugin authors may want to append notes (not replace) on why object caching is recommended for their plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notes The notes appended to the health check description.
	 * @param array $available_services The list of available persistent object cache services.
	 */
	$notes = apply_filters( 'site_status_persistent_object_cache_notes', $notes, $available_services );

	$result['status']         = 'recommended';
	$result['label']          = __( 'You should use a persistent object cache', 'performance-lab' );
	$result['badge']['color'] = 'orange';
	$result['description']   .= sprintf(
		'<p>%s</p>',
		wp_kses(
			$notes,
			array(
				'a'      => array( 'href' => true ),
				'code'   => true,
				'em'     => true,
				'strong' => true,
			)
		)
	);

	return $result;
}

/**
 * Callback for `site_status_persistent_object_cache` filter.
 *
 * Determines whether to suggest using a persistent object cache.
 *
 * @since 1.0.0
 *
 * @param mixed $should_suggest Whether to suggest using a persistent object cache.
 * @return bool Whether to suggest using a persistent object cache.
 */
function perflab_oc_health_should_persistent_object_cache( $should_suggest ) {
	global $wpdb;

	// Bypass expensive calls if `$should_suggest` was already set truthy.
	if ( $should_suggest ) {
		return true;
	}

	/**
	 * Filter the thresholds used to determine whether to suggest the use of a persistent object cache.
	 *
	 * @since 1.0.0
	 *
	 * @param array $thresholds The list of threshold names and numbers.
	 */
	$thresholds = apply_filters(
		'site_status_persistent_object_cache_thresholds',
		array(
			'alloptions_count' => 500,
			'alloptions_bytes' => 100000,
			'comments_count'   => 1000,
			'options_count'    => 1000,
			'posts_count'      => 1000,
			'terms_count'      => 1000,
			'users_count'      => 1000,
		)
	);

	$alloptions = wp_load_alloptions();

	if ( $thresholds['alloptions_count'] < count( $alloptions ) ) {
		return true;
	}

	if ( $thresholds['alloptions_bytes'] < strlen( serialize( $alloptions ) ) ) {
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

	$threshold_map = array(
		'comments_count' => $wpdb->comments,
		'options_count'  => $wpdb->options,
		'posts_count'    => $wpdb->posts,
		'terms_count'    => $wpdb->terms,
		'users_count'    => $wpdb->users,
	);

	foreach ( $threshold_map as $threshold => $table ) {
		if ( $thresholds[ $threshold ] <= $results[ $table ]->rows ) {
			return true;
		}
	}

	return false;
}
add_filter( 'site_status_suggest_persistent_object_cache', 'perflab_oc_health_should_persistent_object_cache', 20 );

/**
 * Returns a list of available persistent object cache services.
 *
 * @since 1.0.0
 *
 * @return array The list of available persistent object cache services.
 */
function perflab_oc_health_available_object_cache_services() {
	$extensions = array_map(
		'extension_loaded',
		array(
			'APCu'      => 'apcu',
			'Redis'     => 'redis',
			'Relay'     => 'relay',
			'Memcached' => 'memcache', // The `memcached` extension seems unmaintained.
		)
	);

	$services = array_keys( array_filter( $extensions ) );

	/**
	 * Filter the persistent object cache services available to the user.
	 *
	 * This can be useful to hide or add services not included in the defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param array $services The list of available persistent object cache services.
	 */
	return apply_filters( 'site_status_available_object_cache_services', $services );
}
