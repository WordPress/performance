<?php
/**
 * Admin hooks used for SQLite Integration.
 *
 * @package performance-lab
 * @since 2.1.0
 */

/**
 * Adds admin notices.
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

/**
 * Adds a link to the admin bar.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param WP_Admin_Bar $admin_bar The admin bar object.
 */
function perflab_sqlite_plugin_adminbar_item( $admin_bar ) {
	global $wpdb;

	if ( defined( 'PERFLAB_SQLITE_DB_DROPIN_VERSION' ) && defined( 'DATABASE_TYPE' ) && 'sqlite' === DATABASE_TYPE ) {
		$title = '<span style="color:#46B450;">' . __( 'Database: SQLite', 'performance-lab' ) . '</span>';
	} elseif ( stripos( $wpdb->db_server_info(), 'maria' ) !== false ) {
		$title = '<span style="color:#DC3232;">' . __( 'Database: MariaDB', 'performance-lab' ) . '</span>';
	} else {
		$title = '<span style="color:#DC3232;">' . __( 'Database: MySQL', 'performance-lab' ) . '</span>';
	}

	$args = array(
		'id'     => 'performance-lab-sqlite',
		'parent' => 'top-secondary',
		'title'  => $title,
		'href'   => esc_url( admin_url( 'options-general.php?page=' . PERFLAB_MODULES_SCREEN ) ),
		'meta'   => false,
	);
	$admin_bar->add_node( $args );
}
add_action( 'admin_bar_menu', 'perflab_sqlite_plugin_adminbar_item', 999 );
