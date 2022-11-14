<?php
/**
 * Module Name: SQLite Integration
 * Description: Use an SQLite database instead of MySQL.
 * Disabled Notice: Enabling this module will bring up the WordPress installation screen. You will need to reconfigure your site, and start with a fresh site. Disabling the module you will get back to your previous MySQL database, with all your previous data intact.
 * Enabled Notice: Your site is currently using an SQLite database. You can disable this module to get back to your previous MySQL database, with all your previous data intact.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Require the site-health tweaks.
 */
require_once __DIR__ . '/site-health.php';

/**
 * Add admin notices.
 *
 * When the plugin gets merged in wp-core, this is not to be ported.
 *
 * @since n.e.x.t
 */
function perflab_sqlite_plugin_admin_notice() {
	// Check if the wp-content/db.php file exists.
	if ( ! file_exists( WP_CONTENT_DIR . '/db.php' ) ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				/* translators: %1$s: <code>wp-content/db.php</code>, %2$s: The admin-URL to deactivate the module. */
				__( 'The SQLite Integration module is active, but the %1$s file is missing. Please <a href="%2$s">deactivate the module</a> and re-activate it to try again.', 'performance-lab' ),
				'<code>wp-content/db.php</code>',
				esc_url( admin_url( 'options-general.php?page=perflab-modules' ) )
			)
		);
	}
}
add_action( 'admin_notices', 'perflab_sqlite_plugin_admin_notice' ); // Add the admin notices.
