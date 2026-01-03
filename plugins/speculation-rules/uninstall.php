<?php
/**
 * Plugin uninstaller logic.
 *
 * @package speculation-rules
 * @since 1.2.0
 */

// If uninstall.php is not called by WordPress, bail.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;// @codeCoverageIgnore
}

// For a multisite, delete the option for all sites (however limited to 100 sites to avoid memory limit or timeout problems in large scale networks).
if ( is_multisite() ) {
	array_map(
		static function ( $site_id ): void {
			switch_to_blog( $site_id );
			plsr_delete_plugin_option();
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

plsr_delete_plugin_option();

/**
 * Delete the current site's option.
 *
 * @since 1.2.0
 */
function plsr_delete_plugin_option(): void {
	delete_option( 'plsr_speculation_rules' );
}
