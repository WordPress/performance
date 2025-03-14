<?php
/**
 * Helper functions for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Initializes extensions for Optimization Detective.
 *
 * @since 0.7.0
 * @access private
 */
function od_initialize_extensions(): void {
	/**
	 * Fires when extensions to Optimization Detective can be loaded and initialized.
	 *
	 * @since 0.7.0
	 *
	 * @param string $version Optimization Detective version.
	 */
	do_action( 'od_init', OPTIMIZATION_DETECTIVE_VERSION );
}

/**
 * Generates a media query for the provided minimum and maximum viewport widths.
 *
 * This helper function is available for extensions to leverage when manually printing STYLE rules via
 * {@see OD_HTML_Tag_Processor::append_head_html()} or {@see OD_HTML_Tag_Processor::append_body_html()}
 *
 * @since 0.7.0
 *
 * @param int<0, max>|null $minimum_viewport_width Minimum viewport width (exclusive).
 * @param int<1, max>|null $maximum_viewport_width Maximum viewport width (inclusive).
 * @return non-empty-string|null Media query, or null if the min/max were both unspecified or invalid.
 */
function od_generate_media_query( ?int $minimum_viewport_width, ?int $maximum_viewport_width ): ?string {
	if ( is_int( $minimum_viewport_width ) && is_int( $maximum_viewport_width ) && $minimum_viewport_width >= $maximum_viewport_width ) {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'The minimum width cannot be greater than or equal to the maximum width.', 'optimization-detective' ), 'Optimization Detective 0.7.0' );
		return null;
	}
	$has_min_width = ( null !== $minimum_viewport_width && $minimum_viewport_width > 0 );
	$has_max_width = ( null !== $maximum_viewport_width && PHP_INT_MAX !== $maximum_viewport_width ); // Note: The use of PHP_INT_MAX is obsolete.
	if ( $has_min_width && $has_max_width ) {
		return sprintf( '(%dpx < width <= %dpx)', $minimum_viewport_width, $maximum_viewport_width );
	} elseif ( $has_min_width ) {
		return sprintf( '(%dpx < width)', $minimum_viewport_width );
	} elseif ( $has_max_width ) {
		return sprintf( '(width <= %dpx)', $maximum_viewport_width );
	} else {
		return null;
	}
}

/**
 * Displays the HTML generator meta tag for the Optimization Detective plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.0
 * @access private
 */
function od_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	$content = 'optimization-detective ' . OPTIMIZATION_DETECTIVE_VERSION;

	// Indicate that the plugin will not be doing anything because the REST API is unavailable.
	if ( od_is_rest_api_unavailable() ) {
		$content .= '; rest_api_unavailable';
	}

	echo '<meta name="generator" content="' . esc_attr( $content ) . '">' . "\n";
}

/**
 * Gets the path to a script or stylesheet.
 *
 * @since 0.9.0
 * @access private
 *
 * @param string      $src_path Source path, relative to plugin root.
 * @param string|null $min_path Minified path. If not supplied, then '.min' is injected before the file extension in the source path.
 * @return string URL to script or stylesheet.
 *
 * @noinspection PhpDocMissingThrowsInspection
 */
function od_get_asset_path( string $src_path, ?string $min_path = null ): string {
	if ( null === $min_path ) {
		// Note: wp_scripts_get_suffix() is not used here because we need access to both the source and minified paths.
		$min_path = (string) preg_replace( '/(?=\.\w+$)/', '.min', $src_path );
	}

	$force_src = false;
	if ( WP_DEBUG && ! file_exists( trailingslashit( __DIR__ ) . $min_path ) ) {
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
				__( 'Minified asset has not been built: %s', 'optimization-detective' ),
				$min_path
			),
			E_USER_WARNING
		);
	}

	if ( SCRIPT_DEBUG || $force_src ) {
		return $src_path;
	}

	return $min_path;
}
