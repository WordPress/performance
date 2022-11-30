<?php
/**
 * Actions to run when the module gets activated.
 *
 * @since n.e.x.t
 * @package performance-lab
 */

/**
 * Copies the db.php file in wp-content and reloads the page.
 *
 * @since n.e.x.t
 */
return function() {
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

	// Copy the file, replacing contents as needed.
	if ( $wp_filesystem->touch( $destination ) ) {

		// Get the db.copy file contents, replace placeholders and write it to the destination.
		$file_contents = str_replace(
			'{SQLITE_IMPLEMENTATION_FOLDER_PATH}',
			__DIR__,
			file_get_contents( __DIR__ . '/db.copy' )
		);

		$wp_filesystem->put_contents( $destination, $file_contents );
	}

	// Load SQLite constants and bail if database file already exists.
	require_once __DIR__ . '/constants.php';
	if ( file_exists( FQDB ) ) {
		return;
	}

	// Otherwise install WordPress in the SQLite database before the redirect,
	// so that the user does not end up on the WordPress installation screen
	// but rather remains in the Performance Lab modules screen.
	add_filter(
		'wp_redirect',
		function( $redirect_location ) {
			// Get current basic setup data to install WordPress in the new DB.
			$blog_title = get_bloginfo( 'name' );
			$is_public  = (bool) get_option( 'blog_public' );
			$language   = get_option( 'WPLANG' );
			$user_email = get_option( 'admin_email' );
			$admin_user = get_user_by( 'email', $user_email );
			if ( ! $admin_user ) {
				$admin_user = wp_get_current_user();
			}
			$user_name     = $admin_user->user_login;
			$user_password = $admin_user->user_pass;

			// Get current data to keep the Performance Lab plugin and relevant
			// modules active in the new DB.
			$active_plugins_option = get_option( 'active_plugins', array() );
			$pl_index              = array_search( plugin_basename( PERFLAB_MAIN_FILE ), $active_plugins_option, true );
			$active_plugins_option = false !== $pl_index ? array( $active_plugins_option[ $pl_index ] ) : array();
			$active_modules_option = get_option( PERFLAB_MODULES_SETTING, array() );

			// If the current user is the admin user, attempt to keep them
			// logged-in by retaining their current session. Depending on the
			// site configuration, this is not 100% reliable as sites may store
			// session tokens outside of user meta. However that does not lead
			// to any problem, the user would simply be required to sign in
			// again.
			if ( (int) get_current_user_id() === (int) $admin_user->ID ) {
				$admin_sessions = get_user_meta( $admin_user->ID, 'session_tokens', true );
			}

			// Load and set up SQLite database.
			require_once __DIR__ . '/wp-includes/sqlite/db.php';
			wp_set_wpdb_vars();

			// Load WordPress installation API functions.
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// Install WordPress in the SQLite database with the same base
			// configuration as the MySQL database. Also copy over the
			// Performance Lab modules configuration.

			// Since $user_password is already hashed, add a filter to ensure
			// it is inserted into the database like that, instead of being
			// re-hashed.
			$unhash_user_pass = function( $data, $update, $user_id, $userdata ) use ( $user_password ) {
				// Double check this is actually the already hashed password,
				// to prevent any chance of accidentally putting another
				// password into the database which would then be plain text.
				if ( ! empty( $userdata['user_pass'] ) && $userdata['user_pass'] === $user_password ) {
					$data['user_pass'] = $userdata['user_pass'];
				}
				return $data;
			};
			add_filter( 'wp_pre_insert_user_data', $unhash_user_pass, 10, 4 );
			wp_install( $blog_title, $user_name, $user_email, $is_public, '', $user_password, $language );
			remove_filter( 'wp_pre_insert_user_data', $unhash_user_pass, 10 );

			// Activate the Performance Lab plugin and its modules.
			update_option( 'active_plugins', $active_plugins_option );
			update_option( PERFLAB_MODULES_SETTING, $active_modules_option );

			if ( isset( $admin_sessions ) && $admin_sessions ) {
				$admin_user = get_user_by( 'login', $user_name );
				if ( $admin_user ) {
					update_user_meta( $admin_user->ID, 'session_tokens', $admin_sessions );
				}
			}

			return $redirect_location;
		}
	);
};
