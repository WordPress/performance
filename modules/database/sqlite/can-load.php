<?php
/**
 * Can load function to determine if SQLite can be activated.
 *
 * @since n.e.x.t
 * @package performance-lab
 */

/**
 * Checks whether the given module can be activated.
 *
 * @since n.e.x.t
 */
return function() {
	if ( ! wp_is_writable( WP_CONTENT_DIR ) ) {
		return sprintf(
			/* translators: %s: WP_CONTENT_DIR */
			__( 'The SQLite module cannot be activated because the %s directory is not writable.', 'performance-lab' ),
			WP_CONTENT_DIR
		);
	}

	if ( ! extension_loaded( 'sqlite3' ) || ! class_exists( 'SQLite3' ) ) {
		return __( 'The SQLite module cannot be activated because the SQLite extension is not loaded.', 'performance-lab' );
	}

	return true;
};
