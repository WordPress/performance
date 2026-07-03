<?php
/**
 * Plugin uninstaller logic.
 *
 * @package view-transitions
 * @since 1.0.0
 */

declare( strict_types = 1 );

// If uninstall.php is not called by WordPress, bail.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;// @codeCoverageIgnore
}

// For a multisite, delete the option for all sites (however limited to 100 sites to avoid memory limit or timeout problems in large scale networks).
if ( is_multisite() ) {
	$plvt_site_ids = get_sites(
		array(
			'fields'                 => 'ids',
			'number'                 => 100,
			'update_site_cache'      => false,
			'update_site_meta_cache' => false,
		)
	);

	foreach ( $plvt_site_ids as $plvt_site_id ) {
		switch_to_blog( $plvt_site_id ); // @phpstan-ignore argument.type (get_sites( 'fields' => 'ids' ) returns int[], but php-stubs/wordpress-stubs uses a sealed array shape in its conditional return type so the narrowing is lost when extra args are passed. TODO: Fix upstream in php-stubs/wordpress-stubs and remove.)
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
