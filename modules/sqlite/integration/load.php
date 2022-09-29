<?php
/**
 * Module Name: SQLite Integration
 * Description: Use an SQLite database instead of MySQL. CAUTION: Enabling this module will bring up the WordPress installation screen. You will need to reconfigure your site, and you will lose all your data. If you then disable the module, you will get back to your previous MySQL database, with all your previous data intact.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since x.x.x
 */

// Temporary - This will be in wp-config.php once SQLite is merged in Core.
if ( ! defined( 'DATABASE_TYPE' ) ) {
	define( 'DATABASE_TYPE', 'sqlite' );
}

/**
 * Add the db.php file in wp-content.
 *
 * When the plugin gets merged in wp-core, this is not to be ported.
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

	// Bail early if there is a `.delete-db` file.
	if ( file_exists( WP_CONTENT_DIR . '/.delete-db' ) ) {
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
				'{PERFLAB_MODULES_SETTING}'
			),
			array(
				__DIR__,
				str_replace( WP_PLUGIN_DIR . '/', '', PERFLAB_MAIN_FILE ),
				'sqlite/integration',
				PERFLAB_MODULES_SETTING
			),
			file_get_contents( __DIR__ . '/db.copy' )
		);
		$wp_filesystem->put_contents( $destination, $file_contents );

		wp_safe_redirect( wp_login_url() );
		wp_die();
	}
}

add_action( 'init', function() {
	// If there is a `.delete-db` file, delete it and deactivate the module.
	if ( file_exists( WP_CONTENT_DIR . '/.delete-db' ) ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( file_exists( WP_CONTENT_DIR . '/db.php' ) ) {
			$wp_filesystem->delete( WP_CONTENT_DIR . '/db.php' );
			wp_safe_redirect( wp_login_url() );
			wp_die();
		}

		$modules = get_option( PERFLAB_MODULES_SETTING, array() );
		unset( $modules['sqlite/integration'] );
		update_option( PERFLAB_MODULES_SETTING, $modules );
		$wp_filesystem->delete( WP_CONTENT_DIR . '/.delete-db' );
	}

	sqlite_plugin_copy_db_file(); // Copy db.php file.
} );

/**
 * Trigger actions when the module gets deactivated.
 *
 * @param string $option    Name of the option.
 * @param mixed  $old_value Old value of the option.
 * @param mixed  $value     New value of the option.
 */
function perflab_sqlite_module_activation_deactivation( $value, $old_value ) {
	$disabled = ! isset( $value['sqlite/integration'] );

	if ( $disabled ) {
		if ( file_exists( WP_CONTENT_DIR . '/db.php' ) ) {
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . '/wp-admin/includes/file.php';
				WP_Filesystem();
			}
			$wp_filesystem->delete( WP_CONTENT_DIR . '/db.php' );
			$wp_filesystem->touch( WP_CONTENT_DIR . '/.delete-db' );
		}
	}
}
add_action( 'pre_update_option_' . PERFLAB_MODULES_SETTING, 'perflab_sqlite_module_activation_deactivation',10, 3 );

/**
 * Add admin notices.
 *
 * When the plugin gets merged in wp-core, this is not to be ported.
 */
function sqlite_plugin_admin_notice() {

	// If SQLite is not detected, bail early.
	if ( ! class_exists( 'SQLite3' ) ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'The SQLite Integration module is active, but the SQLite3 class is missing from your server. Please make sure that SQLite is enabled in your PHP installation.', 'performance-lab' )
		);
		return;
	}

	// Check if the wp-content/db.php file exists.
	if ( ! file_exists( WP_CONTENT_DIR . '/db.php' ) ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				__( 'The SQLite Integration module is active, but the %1$s file is missing. Please <a href="%2$s">deactivate the module</a> and re-activate it to try again.', 'performance-lab' ),
				'<code>wp-content/db.php</code>',
				esc_url( admin_url( 'options-general.php?page=perflab-modules' ) )
			)
		);
	}
}
add_action( 'admin_notices', 'sqlite_plugin_admin_notice' ); // Add the admin notices.
