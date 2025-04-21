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
	 * @link https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/hooks.md#:~:text=Action%3A%20od_init
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
 * Gets the reasons why Optimization Detective is disabled for the current response.
 *
 * @since n.e.x.t
 * @access private
 *
 * @return array{
 *     is_search?: string,
 *     is_embed?: string,
 *     is_preview?: string,
 *     is_customize_preview?: string,
 *     not_get_request?: string,
 *     no_cache_purge_post_id?: string,
 *     filter_disabled?: string,
 *     rest_api_unavailable?: string,
 *     query_param_disabled?: string
 * } Array of disabled reason codes and their messages.
 */
function od_get_disabled_reasons(): array {
	$disabled_flags = array(
		'is_search'              => false,
		'is_embed'               => false,
		'is_preview'             => false,
		'is_customize_preview'   => false,
		'not_get_request'        => false,
		'no_cache_purge_post_id' => false,
	);

	// Disable the search template since there is no predictability in whether posts in the loop will have featured images assigned or not. If a
	// theme template for search results doesn't even show featured images, then this wouldn't be an issue.
	if ( is_search() ) {
		$disabled_flags['is_search'] = true;
	}

	// Avoid optimizing embed responses because the Post Embed iframes include a sandbox attribute with the value of
	// "allow-scripts" but without "allow-same-origin". This can result in an error in the console:
	// > Access to script at '.../detect.js?ver=0.4.1' from origin 'null' has been blocked by CORS policy: No 'Access-Control-Allow-Origin' header is present on the requested resource.
	// So it's better to just avoid attempting to optimize Post Embed responses (which don't need optimization anyway).
	if ( is_embed() ) {
		$disabled_flags['is_embed'] = true;
	}

	// Skip posts that aren't published yet.
	if ( is_preview() ) {
		$disabled_flags['is_preview'] = true;
	}

	// Disable in Customizer preview since injection of inline-editing controls can interfere with XPath. Optimization is also not necessary in this context.
	if ( is_customize_preview() ) {
		$disabled_flags['is_customize_preview'] = true;
	}

	// Disable for POST responses since since they cannot, by definition, be cached.
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
		$disabled_flags['not_get_request'] = true;
	}

	// Disable when there is no post ID available for cache purging. Page caching plugins can only reliably be told to invalidate a cached page when a post is available to trigger
	// the relevant actions on.
	if ( null === od_get_cache_purge_post_id() ) {
		$disabled_flags['no_cache_purge_post_id'] = true;
	}

	// Check if any flags are set to true.
	$has_disabled_flags = count( array_filter( $disabled_flags ) ) > 0;

	/**
	 * Filters whether the current response can be optimized.
	 *
	 * @since 0.1.0
	 * @since n.e.x.t Added $disabled_flags parameter
	 * @link https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/hooks.md#:~:text=Filter%3A%20od_can_optimize_response
	 *
	 * @param bool $can_optimize Whether response can be optimized.
	 * @param array{
	 *     is_search: bool,
	 *     is_embed: bool,
	 *     is_preview: bool,
	 *     is_customize_preview: bool,
	 *     not_get_request: bool,
	 *     no_cache_purge_post_id: bool
	 * } $disabled_flags Flags indicating which conditions are disabling optimization.
	 */
	$can_optimize = (bool) apply_filters( 'od_can_optimize_response', ! $has_disabled_flags, $disabled_flags );

	$reasons = array();
	if ( ! $can_optimize ) {
		$reason_messages = array(
			'is_search'              => __( 'Page is not optimized because it is a search results page.', 'optimization-detective' ),
			'is_embed'               => __( 'Page is not optimized because it is an embed.', 'optimization-detective' ),
			'is_preview'             => __( 'Page is not optimized because it is a preview.', 'optimization-detective' ),
			'is_customize_preview'   => __( 'Page is not optimized because it is a customize preview.', 'optimization-detective' ),
			'not_get_request'        => __( 'Page is not optimized because it is not a GET request.', 'optimization-detective' ),
			'no_cache_purge_post_id' => __( 'Page is not optimized because there is no post ID available for cache purging.', 'optimization-detective' ),
		);

		$reasons = wp_array_slice_assoc( $reason_messages, array_keys( array_filter( $disabled_flags ) ) );

		// If no technical reasons but optimization still disabled, it's because of the filter.
		if ( 0 === count( $reasons ) ) {
			$reasons['filter_disabled'] = __( 'Page is not optimized because the od_can_optimize_response filter returned false.', 'optimization-detective' );
		}
	}

	if ( od_is_rest_api_unavailable() && ! ( wp_get_environment_type() === 'local' && ! function_exists( 'tests_add_filter' ) ) ) {
		$reasons['rest_api_unavailable'] = __( 'Page is not optimized because the REST API for storing URL Metrics is not available.', 'optimization-detective' );
	}

	if ( isset( $_GET['optimization_detective_disabled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$reasons['query_param_disabled'] = __( 'Page is not optimized because the URL has the optimization_detective_disabled query parameter.', 'optimization-detective' );
	}

	return $reasons;
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

	// Add any reasons why Optimization Detective is disabled.
	$disabled_reasons = od_get_disabled_reasons();
	if ( count( $disabled_reasons ) > 0 ) {
		$flags    = array_keys( $disabled_reasons );
		$content .= '; ' . implode( '; ', $flags );
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
