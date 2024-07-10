<?php
/**
 * Plugin uninstaller logic.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

// If uninstall.php is not called by WordPress, bail.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Deletes all Performance Dashboard posts.
 *
 * @since 0.1.0
 */
function performance_dashboard_delete_all_posts(): void {
	global $wpdb;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Delete all related post meta for URL Metrics posts.
	$wpdb->query(
		$wpdb->prepare(
			"
				DELETE meta
				FROM $wpdb->postmeta AS meta
					INNER JOIN $wpdb->posts AS posts
						ON posts.ID = meta.post_id
				WHERE posts.post_type = %s;
				",
			'perf-dash-data'
		)
	);

	// Delete all URL Metrics posts.
	$wpdb->delete(
		$wpdb->posts,
		array(
			'post_type' => 'perf-dash-data',
		)
	);

	wp_cache_set_posts_last_changed();

	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

performance_dashboard_delete_all_posts();

/*
 * For a multisite, delete the posts for all other sites (however limited to 100 sites to avoid memory limit or
 * timeout problems in large scale networks).
 */
if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields'                 => 'ids',
			'number'                 => 100,
			'update_site_cache'      => false,
			'update_site_meta_cache' => false,
		)
	);

	// Skip iterating over self.
	$site_ids = array_diff(
		$site_ids,
		array( get_current_blog_id() )
	);

	// Delete all other sites' performance dashboard data.
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		performance_dashboard_delete_all_posts();
		restore_current_blog();
	}
}
