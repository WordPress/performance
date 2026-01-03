<?php
/**
 * Plugin uninstaller logic.
 *
 * @package webp-uploads
 * @since 1.1.0
 */

// If uninstall.php is not called by WordPress, bail.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// For a multisite, delete the option for all sites (however limited to 100 sites to avoid memory limit or timeout problems in large scale networks).
if ( is_multisite() ) {
	array_map(
		static function ( $site_id ): void {
			switch_to_blog( $site_id );
			webp_uploads_delete_plugin_option();
			restore_current_blog();
		},
		get_sites(
			array(
				'fields'                 => 'ids',
				'number'                 => 100,
				'update_site_cache'      => false,
				'update_site_meta_cache' => false,
			)
		)
	);
}

webp_uploads_delete_plugin_option();

/**
 * Delete the current site's option.
 *
 * @since 1.1.0
 */
function webp_uploads_delete_plugin_option(): void {
	delete_option( 'perflab_generate_webp_and_jpeg' );
	delete_option( 'perflab_generate_all_fallback_sizes' );
}
