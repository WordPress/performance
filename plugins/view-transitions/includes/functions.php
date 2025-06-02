<?php
/**
 * Utility functions for View Transitions.
 *
 * @package view-transitions
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Gets the path to a script or stylesheet.
 *
 * @since 1.0.0
 * @access private
 *
 * @param string      $src_path Source path, relative to plugin root.
 * @param string|null $min_path Minified path. If not supplied, then '.min' is injected before the file extension in the source path.
 * @return string Full path to script or stylesheet.
 *
 * @noinspection PhpDocMissingThrowsInspection
 */
function plvt_get_asset_path( string $src_path, ?string $min_path = null ): string {
	if ( null === $min_path ) {
		// Note: wp_scripts_get_suffix() is not used here because we need access to both the source and minified paths.
		$min_path = (string) preg_replace( '/(?=\.\w+$)/', '.min', $src_path );
	}

	$plugin_dir = trailingslashit( dirname( __DIR__ ) );

	$force_src = false;
	if ( WP_DEBUG && ! file_exists( $plugin_dir . $min_path ) ) {
		$force_src = true;
		/**
		 * No WP_Exception is thrown by wp_trigger_error() since E_USER_ERROR is not passed as the error level.
		 *
		 * @noinspection PhpUnhandledExceptionInspection
		 */
		wp_trigger_error(
			__FUNCTION__,
			sprintf(
				/* translators: %s is the minified asset path */
				__( 'Minified asset has not been built: %s', 'view-transitions' ),
				$min_path
			),
			E_USER_WARNING
		);
	}

	if ( SCRIPT_DEBUG || $force_src ) {
		return $plugin_dir . $src_path;
	}

	return $plugin_dir . $min_path;
}
