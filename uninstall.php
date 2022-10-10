<?php
/**
 * Plugin uninstaller logic.
 *
 * @package performance-lab
 * @since 1.2.0
 */

// If uninstall.php is not called by WordPress, bail.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
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
		perflab_delete_plugin_option();
		restore_current_blog();
	}
}

perflab_delete_plugin_option();

/**
 * Delete the current site's option.
 *
 * @since 1.4.0
 */
function perflab_delete_plugin_option() {
	delete_option( 'perflab_modules_settings' );
	delete_option( 'perflab_generate_webp_and_jpeg' );
}
