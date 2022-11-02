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
};
