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

require_once __DIR__ . '/class-od-url-metrics-post-type.php';

$od_delete_site_data = static function () {
	// Delete all URL Metrics posts for the current site.
	OD_URL_Metrics_Post_Type::delete_all_posts();
	wp_unschedule_hook( OD_URL_Metrics_Post_Type::GC_CRON_EVENT_NAME );
};

$od_delete_site_data();

/*
 * For a multisite, delete the URL Metrics for all other sites (however limited to 100 sites to avoid memory limit or
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

	// Delete all other blogs' URL Metrics posts.
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		$od_delete_site_data();
		restore_current_blog();
	}
}
