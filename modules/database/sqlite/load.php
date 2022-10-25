<?php
/**
 * Module Name: SQLite Integration
 * Description: Use an SQLite database instead of MySQL. <div style="background:#fff;border:1px solid #c3c4c7;border-left-width: 4px;border-left-color:#dba617;box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);padding:1em;max-width:50em;">CAUTION: Enabling this module will bring up the WordPress installation screen. You will need to reconfigure your site, and you will lose all your data. If you then disable the module, you will get back to your previous MySQL database, with all your previous data intact.</div>
 * Experimental: Yes
 *
 * @package performance-lab
 * @since n.e.x.t
 */

// Temporary - This will be in wp-config.php once SQLite is merged in Core.
if ( ! defined( 'DATABASE_TYPE' ) ) {
	define( 'DATABASE_TYPE', 'sqlite' );
}

/**
 * Adds the db.php file in wp-content.
 *
 * When the plugin gets merged in wp-core, this is not to be ported.
 *
 * @since n.e.x.t
 */
function sqlite_plugin_copy_db_file() {
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
				'sqlite/integration',
				PERFLAB_MODULES_SETTING,
			),
			file_get_contents( __DIR__ . '/db.copy' )
		);
		$wp_filesystem->put_contents( $destination, $file_contents );
	}
}
add_action( 'plugins_loaded', 'sqlite_plugin_copy_db_file' );

/**
 * Trigger actions when the module gets deactivated.
 *
 * @since n.e.x.t
 *
 * @param mixed $value New value of the option.
 *
 * @return mixed Returns the value.
 */
function perflab_sqlite_module_deactivation( $value ) {
	if ( ! isset( $value['sqlite/integration'] ) ) {
		if ( file_exists( WP_CONTENT_DIR . '/db.php' ) ) {
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . '/wp-admin/includes/file.php';
				WP_Filesystem();
			}
			$wp_filesystem->delete( WP_CONTENT_DIR . '/db.php' );
		}

		// Run an action on `shutdown`, to deactivate the option in the MySQL database.
		add_action(
			'shutdown',
			function() {
				global $table_prefix;

				// Remove the filter to avoid an infinite loop.
				remove_filter( 'pre_update_option_' . PERFLAB_MODULES_SETTING, 'perflab_sqlite_module_deactivation', 10 );

				// Get credentials for the MySQL database.
				$dbuser     = defined( 'DB_USER' ) ? DB_USER : '';
				$dbpassword = defined( 'DB_PASSWORD' ) ? DB_PASSWORD : '';
				$dbname     = defined( 'DB_NAME' ) ? DB_NAME : '';
				$dbhost     = defined( 'DB_HOST' ) ? DB_HOST : '';

				// Init a connection to the MySQL database.
				$wpdb_mysql = new wpdb( $dbuser, $dbpassword, $dbname, $dbhost );
				$wpdb_mysql->set_prefix( $table_prefix );

				// Get the perflab options, remove the sqlite/integration module and update the option.
				$alloptions = $wpdb_mysql->get_results( "SELECT option_name, option_value FROM $wpdb_mysql->options WHERE autoload = 'yes'" );
				$value      = array();
				foreach ( $alloptions as $o ) {
					if ( PERFLAB_MODULES_SETTING === $o->option_name ) {
						$value = maybe_unserialize( $o->option_value );
						break;
					}
				}
				unset( $value['sqlite/integration'] );
				$wpdb_mysql->update(
					$wpdb_mysql->options,
					array( 'option_value' => maybe_serialize( $value ) ),
					array( 'option_name' => PERFLAB_MODULES_SETTING )
				);
			}
		);
	}
	return $value;
}
add_filter( 'pre_update_option_' . PERFLAB_MODULES_SETTING, 'perflab_sqlite_module_deactivation', 10, 3 );

/**
 * Add admin notices.
 *
 * When the plugin gets merged in wp-core, this is not to be ported.
 *
 * @since n.e.x.t
 */
function sqlite_plugin_admin_notice() {
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
add_action( 'admin_notices', 'sqlite_plugin_admin_notice' ); // Add the admin notices.
