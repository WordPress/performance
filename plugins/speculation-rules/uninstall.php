<?php
/**
 * Plugin uninstaller logic.
 *
 * @package speculation-rules
 * @since 1.2.0
 */

declare( strict_types = 1 );

// If uninstall.php is not called by WordPress, bail.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;// @codeCoverageIgnore
}

// For a multisite, delete the option for all sites (however limited to 100 sites to avoid memory limit or timeout problems in large scale networks).
if ( is_multisite() ) {
	$plsr_site_ids = get_sites(
		array(
			'fields'                 => 'ids',
			'number'                 => 100,
			'update_site_cache'      => false,
			'update_site_meta_cache' => false,
		)
	);

	foreach ( $plsr_site_ids as $plsr_site_id ) {
		switch_to_blog( $plsr_site_id ); // @phpstan-ignore argument.type (get_sites( 'fields' => 'ids' ) returns int[], but php-stubs/wordpress-stubs uses a sealed array shape in its conditional return type so the narrowing is lost when extra args are passed. TODO: Fix upstream in php-stubs/wordpress-stubs and remove.)
		plsr_delete_plugin_option();
		restore_current_blog();
	}
}

plsr_delete_plugin_option();

/**
 * Delete the current site's option.
 *
 * @since 1.2.0
 */
function plsr_delete_plugin_option(): void {
	delete_option( 'plsr_speculation_rules' );
}
