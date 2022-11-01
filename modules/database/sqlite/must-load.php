<?php
/**
 * Functions that should always be loaded, regardless of whether the module is active or not.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

// Require the constants file.
require_once __DIR__ . '/constants.php';

/**
 * Trigger actions when the module gets deactivated.
 *
 * @since n.e.x.t
 *
 * @param string $option    The option name.
 * @param mixed  $old_value Old value of the option.
 * @param mixed  $value     New value of the option.
 *
 * @return void
 */
function perflab_sqlite_module_update_option( $option, $old_value, $value ) {
	if ( PERFLAB_MODULES_SETTING !== $option ) {
		return;
	}

	// Figure out we're activating or deactivating the module.
	$sqlite_was_active   = isset( $old_value['database/sqlite'] ) && ! empty( $old_value['database/sqlite']['enabled'] );
	$sqlite_is_active    = isset( $value['database/sqlite'] ) && ! empty( $value['database/sqlite']['enabled'] );
	$activating_sqlite   = $sqlite_is_active && ! $sqlite_was_active;
	$deactivating_sqlite = ! $sqlite_is_active && $sqlite_was_active;

	// If we're activating the module, copy the db.php file.
	if ( $activating_sqlite ) {
		perflab_sqlite_module_copy_db_file();
	}

	// If we are deactivating the module, delete the db.php file.
	if ( $deactivating_sqlite ) {
		perflab_sqlite_module_delete_db_file();

		// Run an action on `shutdown`, to deactivate the option in the MySQL database.
		add_action( 'shutdown', 'perflab_sqlite_module_deactivate_module_in_mysql' );
	}
	return $value;
}
add_action( 'update_option', 'perflab_sqlite_module_update_option', 10, 3 );

/**
 * Adds the db.php file in wp-content.
 *
 * When the plugin gets merged in wp-core, this is not to be ported.
 *
 * @since n.e.x.t
 */
function perflab_sqlite_module_copy_db_file() {
	// Bail early if the SQLite3 class does not exist.
	if ( ! class_exists( 'SQLite3' ) ) {
		return;
	}

	$destination = WP_CONTENT_DIR . '/db.php';

	// Bail early if the file already exists.
	if ( file_exists( $destination ) ) {
		return;
	}

	// Init the filesystem to allow copying the file.
	global $wp_filesystem;
	if ( ! $wp_filesystem ) {
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
	}

	$file_copied_successfully = false;
	if ( $wp_filesystem->touch( $destination ) ) {

		// Get the db.copy file contents, replace placeholders and write it to the destination.
		$file_contents = str_replace(
			array(
				'{SQLITE_IMPLEMENTATION_FOLDER_PATH}',
				'{PERFLAB_PLUGIN}',
				'{SQLITE_MODULE}',
				'{PERFLAB_MODULES_SETTING}',
			),
			array(
				__DIR__,
				str_replace( WP_PLUGIN_DIR . '/', '', PERFLAB_MAIN_FILE ),
				'database/sqlite',
				PERFLAB_MODULES_SETTING,
			),
			file_get_contents( __DIR__ . '/db.copy' )
		);

		$file_copied_successfully = $wp_filesystem->put_contents( $destination, $file_contents );
	}

	if ( $file_copied_successfully ) {
		/**
		 * This hack is here because it's impossible to redirect properly in PHP,
		 * due to hooks race conditions and the fact that the redirect is done before the output is sent.
		 *
		 * When SQLite gets merged in Core this will be a non-issue.
		 */
		add_action(
			'admin_head',
			function() {
				echo '<script>window.location.reload(true);</script>';
			}
		);
	}
}

/**
 * Deletes the db.php file in wp-content.
 *
 * @since n.e.x.t
 */
function perflab_sqlite_module_delete_db_file() {
	if ( file_exists( WP_CONTENT_DIR . '/db.php' ) ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$wp_filesystem->delete( WP_CONTENT_DIR . '/db.php' );
	}
}

/**
 * Deactivate the module in the MySQL database.
 *
 * @since n.e.x.t
 */
function perflab_sqlite_module_deactivate_module_in_mysql() {
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
	$alloptions = $wpdb_mysql->get_results( "SELECT option_name, option_value FROM $wpdb_mysql->options WHERE autoload = 'yes'" );
	$value      = array();
	foreach ( $alloptions as $o ) {
		if ( PERFLAB_MODULES_SETTING === $o->option_name ) {
			$value = maybe_unserialize( $o->option_value );
			break;
		}
	}
	unset( $value['database/sqlite'] );
	$wpdb_mysql->update(
		$wpdb_mysql->options,
		array( 'option_value' => maybe_serialize( $value ) ),
		array( 'option_name' => PERFLAB_MODULES_SETTING )
	);
}
