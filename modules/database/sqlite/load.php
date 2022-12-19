<?php
/**
 * Module Name: SQLite Integration
 * Description: Use an SQLite database instead of MySQL.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since 1.8.0
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
 * @since 1.8.0
 */
function perflab_sqlite_plugin_admin_notice() {
	// Bail early if the PERFLAB_SQLITE_DB_DROPIN_VERSION is defined.
	if ( defined( 'PERFLAB_SQLITE_DB_DROPIN_VERSION' ) ) {
		return;
	}

	/*
	 * If the PERFLAB_SQLITE_DB_DROPIN_VERSION constant is not defined
	 * but there's a db.php file in the wp-content directory, then the module can't be activated.
	 * The module should not have been activated in the first place
	 * (there's a check in the can-load.php file), but this is a fallback check.
	 */
	if ( file_exists( WP_CONTENT_DIR . '/db.php' ) ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				/* translators: 1: PERFLAB_SQLITE_DB_DROPIN_VERSION constant, 2: db.php drop-in path */
				__( 'The SQLite Integration module is active, but the %1$s constant is missing. It appears you already have another %2$s file present on your site. ', 'performance-lab' ),
				'<code>PERFLAB_SQLITE_DB_DROPIN_VERSION</code>',
				'<code>' . esc_html( basename( WP_CONTENT_DIR ) ) . '/db.php</code>'
			)
		);

		return;
	}

	// The dropin db.php is missing.
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		sprintf(
			/* translators: 1: db.php drop-in path, 2: Admin URL to deactivate the module */
			__( 'The SQLite Integration module is active, but the %1$s file is missing. Please <a href="%2$s">deactivate the module</a> and re-activate it to try again.', 'performance-lab' ),
			'<code>' . esc_html( basename( WP_CONTENT_DIR ) ) . '/db.php</code>',
			esc_url( admin_url( 'options-general.php?page=' . PERFLAB_MODULES_SCREEN ) )
		)
	);
}
add_action( 'admin_notices', 'perflab_sqlite_plugin_admin_notice' ); // Add the admin notices.
