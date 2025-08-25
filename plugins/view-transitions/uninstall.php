<?php
/**
 * Plugin uninstaller logic.
 *
 * @package view-transitions
 * @since 1.0.0
 */

// If uninstall.php is not called by WordPress, bail.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;// @codeCoverageIgnore
}

// For a multisite, delete the option for all sites (however limited to 100 sites to avoid memory limit or timeout problems in large scale networks).
if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields'                 => 'ids',
			'number'                 => 100,
			'update_site_cache'      => false,
			'update_site_meta_cache' => false,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		plvt_delete_plugin_option();
		restore_current_blog();
	}
}

plvt_delete_plugin_option();

/**
 * Deletes the current site's option.
 *
 * @since 1.0.0
 */
function plvt_delete_plugin_option(): void {
	delete_option( 'plvt_view_transitions' );
}
