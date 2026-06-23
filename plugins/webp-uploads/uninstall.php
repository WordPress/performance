<?php
/**
 * Plugin uninstaller logic.
 *
 * @package webp-uploads
 * @since 1.1.0
 */

declare( strict_types = 1 );

// If uninstall.php is not called by WordPress, bail.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// For a multisite, delete the option for all sites (however limited to 100 sites to avoid memory limit or timeout problems in large scale networks).
if ( is_multisite() ) {
	$webp_uploads_site_ids = get_sites(
		array(
			'fields'                 => 'ids',
			'number'                 => 100,
			'update_site_cache'      => false,
			'update_site_meta_cache' => false,
		)
	);

	foreach ( $webp_uploads_site_ids as $webp_uploads_site_id ) {
		switch_to_blog( $webp_uploads_site_id ); // @phpstan-ignore argument.type (get_sites( 'fields' => 'ids' ) returns int[], but php-stubs/wordpress-stubs uses a sealed array shape in its conditional return type so the narrowing is lost when extra args are passed. TODO: Fix upstream in php-stubs/wordpress-stubs and remove.)
		webp_uploads_delete_plugin_option();
		restore_current_blog();
	}
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
