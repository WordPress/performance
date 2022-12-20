<?php
/**
 * Actions to run when the module gets deactivated.
 *
 * @since 1.8.0
 * @package performance-lab
 */

/**
 * Deletes the db.php file, and deactivates the module in the SQLite database.
 *
 * @since 1.8.0
 */
return function() {
	if ( ! defined( 'PERFLAB_SQLITE_DB_DROPIN_VERSION' ) || ! file_exists( WP_CONTENT_DIR . '/db.php' ) ) {
		return;
	}

	global $wp_filesystem;

	require_once ABSPATH . '/wp-admin/includes/file.php';

	// Init the filesystem if needed, then delete custom drop-in.
	if ( $wp_filesystem || WP_Filesystem() ) {
		$wp_filesystem->delete( WP_CONTENT_DIR . '/db.php' );
	}

	// Run an action on `shutdown`, to deactivate the option in the MySQL database.
	add_action(
		'shutdown',
		function() {
			global $table_prefix;

			// Get credentials for the MySQL database.
			$dbuser     = defined( 'DB_USER' ) ? DB_USER : '';
			$dbpassword = defined( 'DB_PASSWORD' ) ? DB_PASSWORD : '';
			$dbname     = defined( 'DB_NAME' ) ? DB_NAME : '';
			$dbhost     = defined( 'DB_HOST' ) ? DB_HOST : '';

			// Init a connection to the MySQL database.
			$wpdb_mysql = new wpdb( $dbuser, $dbpassword, $dbname, $dbhost );
			$wpdb_mysql->set_prefix( $table_prefix );

			// Get the perflab options, remove the database/sqlite module and update the option.
			$row = $wpdb_mysql->get_row( $wpdb_mysql->prepare( "SELECT option_value FROM $wpdb_mysql->options WHERE option_name = %s LIMIT 1", PERFLAB_MODULES_SETTING ) );
			if ( is_object( $row ) ) {
				$value = maybe_unserialize( $row->option_value );
				if ( is_array( $value ) && isset( $value['database/sqlite'] ) ) {
					unset( $value['database/sqlite'] );
					$wpdb_mysql->update( $wpdb_mysql->options, array( 'option_value' => maybe_serialize( $value ) ), array( 'option_name' => PERFLAB_MODULES_SETTING ) );
				}
			}
		}
	);
};
