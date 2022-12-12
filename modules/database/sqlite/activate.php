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

	// Place database drop-in if not present yet, except in case there is
	// another database drop-in present already.
	if ( ! defined( 'PERFLAB_SQLITE_DB_DROPIN_VERSION' ) && ! file_exists( $destination ) ) {
		// Init the filesystem to allow copying the file.
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Copy the file, replacing contents as needed.
		if ( $wp_filesystem->touch( $destination ) ) {

			// Get the db.copy.php file contents, replace placeholders and write it to the destination.
			$file_contents = str_replace(
				'{SQLITE_IMPLEMENTATION_FOLDER_PATH}',
				__DIR__,
				file_get_contents( __DIR__ . '/db.copy.php' )
			);

			$wp_filesystem->put_contents( $destination, $file_contents );
		}
	}

	// As an extra safety check, bail if the current user cannot update
	// (or install) WordPress core.
	if ( ! current_user_can( 'update_core' ) ) {
		return;
	}

	// Otherwise install WordPress in the SQLite database before the redirect,
	// so that the user does not end up on the WordPress installation screen
	// but rather remains in the Performance Lab modules screen.
	add_filter(
		'wp_redirect',
		function( $redirect_location ) {
			// If the SQLite DB already exists, simply ensure the module is
			// active there.
			require_once __DIR__ . '/constants.php';
			if ( file_exists( FQDB ) ) {
				require_once __DIR__ . '/wp-includes/sqlite/db.php';
				wp_set_wpdb_vars();
				global $wpdb;
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", PERFLAB_MODULES_SETTING ) );
				if ( is_object( $row ) ) {
					$value = maybe_unserialize( $row->option_value );
					if ( is_array( $value ) && ( ! isset( $value['database/sqlite'] ) || ! $value['database/sqlite']['enabled'] ) ) {
						$value['database/sqlite'] = array( 'enabled' => true );
						$wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $value ) ), array( 'option_name' => PERFLAB_MODULES_SETTING ) );
					}
				}
				return $redirect_location;
			}

			// Get current basic setup data to install WordPress in the new DB.
			$blog_title = get_bloginfo( 'name' );
			$is_public  = (bool) get_option( 'blog_public' );
			$language   = get_option( 'WPLANG' );
			$user_email = get_option( 'admin_email' );
			$admin_user = get_user_by( 'email', $user_email );
			if ( ! $admin_user ) {
				$admin_user = wp_get_current_user();
			}

			// If the current user is not the admin email user, look up the
			// data for the current user. Additionally, attempt to keep them
			// logged-in by retaining their current session. Depending on the
			// site configuration, this is not 100% reliable as sites may store
			// session tokens outside of user meta. However that does not lead
			// to any problem, the user would simply be required to sign in
			// again.
			$current_user    = null;
			$current_user_id = get_current_user_id();
			if ( $current_user_id !== (int) $admin_user->ID ) {
				$current_user = wp_get_current_user();
			}
			$user_sessions = get_user_meta( $current_user_id, 'session_tokens', true );

			// Get current data to keep the Performance Lab plugin and relevant
			// modules active in the new DB.
			$active_plugins_option = array( plugin_basename( PERFLAB_MAIN_FILE ) );
			$active_modules_option = get_option( PERFLAB_MODULES_SETTING, array() );

			// Load and set up SQLite database.
			require_once __DIR__ . '/wp-includes/sqlite/db.php';
			wp_set_wpdb_vars();

			// Load WordPress installation API functions.
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// Install WordPress in the SQLite database with the same base
			// configuration as the MySQL database.
			// Since $admin_user->user_pass is already hashed, add a filter to
			// ensure it is inserted into the database like that, instead of
			// being re-hashed.
			$unhash_user_pass = function( $data, $update, $user_id, $userdata ) use ( $admin_user, $current_user ) {
				// Double check this is actually the already hashed password,
				// to prevent any chance of accidentally putting another
				// password into the database which would then be plain text.
				if (
					! empty( $userdata['user_pass'] )
					&& (
						$userdata['user_pass'] === $admin_user->user_pass
						|| $current_user && $userdata['user_pass'] === $current_user->user_pass
					)
				) {
					$data['user_pass'] = $userdata['user_pass'];
				}
				return $data;
			};
			add_filter( 'wp_pre_insert_user_data', $unhash_user_pass, 10, 4 );
			wp_install( $blog_title, $admin_user->user_login, $user_email, $is_public, '', $admin_user->user_pass, $language );
			if ( $current_user ) { // Also "copy" current admin user if it's not the admin email owner.
				wp_create_user( $current_user->user_login, $current_user->user_pass, $current_user->user_email );
			}
			remove_filter( 'wp_pre_insert_user_data', $unhash_user_pass );

			// If user sessions are found, migrate them over so that the
			// current user remains logged in.
			if ( $user_sessions ) {
				$session_user = get_user_by( 'login', $current_user ? $current_user->user_login : $admin_user->user_login );
				if ( $session_user ) {
					update_user_meta( $session_user->ID, 'session_tokens', $user_sessions );
				}
			}

			// Activate the Performance Lab plugin and its modules.
			// Use direct database query for Performance Lab modules to
			// prevent module activation logic from firing again.
			update_option( 'active_plugins', $active_plugins_option );
			global $wpdb;
			$wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => PERFLAB_MODULES_SETTING,
					'option_value' => maybe_serialize( $active_modules_option ),
					'autoload'     => 'yes',
				)
			);

			return $redirect_location;
		}
	);
};
