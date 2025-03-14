<?php
/**
 * Helper functions for Image Prioritizer.
 *
 * @package image-prioritizer
 * @since 0.1.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Initializes Image Prioritizer when Optimization Detective has loaded.
 *
 * @since 0.2.0
 * @access private
 *
 * @param string $optimization_detective_version Current version of the optimization detective plugin.
 */
function image_prioritizer_init( string $optimization_detective_version ): void {
	$required_od_version = '0.9.0';
	if ( ! version_compare( (string) strtok( $optimization_detective_version, '-' ), $required_od_version, '>=' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				global $pagenow;
				if ( ! in_array( $pagenow, array( 'index.php', 'plugins.php' ), true ) ) {
					return;
				}
				wp_admin_notice(
					esc_html__( 'The Image Prioritizer plugin requires a newer version of the Optimization Detective plugin. Please update your plugins.', 'image-prioritizer' ),
					array( 'type' => 'warning' )
				);
			}
		);
		return;
	}

	// Classes are required here because only here do we know the expected version of Optimization Detective is active.
	require_once __DIR__ . '/class-image-prioritizer-tag-visitor.php';
	require_once __DIR__ . '/class-image-prioritizer-img-tag-visitor.php';
	require_once __DIR__ . '/class-image-prioritizer-background-image-styled-tag-visitor.php';
	require_once __DIR__ . '/class-image-prioritizer-video-tag-visitor.php';

	add_action( 'wp_head', 'image_prioritizer_render_generator_meta_tag' );
	add_action( 'od_register_tag_visitors', 'image_prioritizer_register_tag_visitors' );
	add_filter( 'od_extension_module_urls', 'image_prioritizer_filter_extension_module_urls' );
	add_filter( 'od_url_metric_schema_root_additional_properties', 'image_prioritizer_add_root_schema_properties' );
	add_filter( 'rest_request_before_callbacks', 'image_prioritizer_filter_rest_request_before_callbacks', 10, 3 );
}

/**
 * Displays the HTML generator meta tag for the Image Prioritizer plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.0
 * @access private
 */
function image_prioritizer_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="image-prioritizer ' . esc_attr( IMAGE_PRIORITIZER_VERSION ) . '">' . "\n";
}

/**
 * Registers tag visitors.
 *
 * @since 0.1.0
 * @access private
 *
 * @param OD_Tag_Visitor_Registry $registry Tag visitor registry.
 */
function image_prioritizer_register_tag_visitors( OD_Tag_Visitor_Registry $registry ): void {
	// Note: The class is invocable (it has an __invoke() method).
	$img_visitor = new Image_Prioritizer_Img_Tag_Visitor();
	$registry->register( 'image-prioritizer/img', $img_visitor );

	$bg_image_visitor = new Image_Prioritizer_Background_Image_Styled_Tag_Visitor();
	$registry->register( 'image-prioritizer/background-image', $bg_image_visitor );

	$video_visitor = new Image_Prioritizer_Video_Tag_Visitor();
	$registry->register( 'image-prioritizer/video', $video_visitor );
}

/**
 * Filters the list of Optimization Detective extension module URLs to include the extension for Image Prioritizer.
 *
 * @since 0.3.0
 * @access private
 *
 * @param string[]|mixed $extension_module_urls Extension module URLs.
 * @return string[] Extension module URLs.
 */
function image_prioritizer_filter_extension_module_urls( $extension_module_urls ): array {
	if ( ! is_array( $extension_module_urls ) ) {
		$extension_module_urls = array();
	}
	$extension_module_urls[] = plugins_url( add_query_arg( 'ver', IMAGE_PRIORITIZER_VERSION, image_prioritizer_get_asset_path( 'detect.js' ) ), __FILE__ );
	return $extension_module_urls;
}

/**
 * Filters additional properties for the root schema for Optimization Detective.
 *
 * @since 0.3.0
 * @access private
 *
 * @param array<string, array{type: string}>|mixed $additional_properties Additional properties.
 * @return array<string, array{type: string}> Additional properties.
 */
function image_prioritizer_add_root_schema_properties( $additional_properties ): array {
	if ( ! is_array( $additional_properties ) ) {
		$additional_properties = array();
	}

	$additional_properties['lcpElementExternalBackgroundImage'] = array(
		'type'       => 'object',
		'properties' => array(
			'url'   => array(
				'type'      => 'string',
				'format'    => 'uri', // Note: This is excessively lax, as it is used exclusively in rest_sanitize_value_from_schema() and not in rest_validate_value_from_schema().
				'pattern'   => '^https?://',
				'required'  => true,
				'maxLength' => 500, // Image URLs can be quite long.
			),
			'tag'   => array(
				'type'      => 'string',
				'required'  => true,
				'minLength' => 1,
				// The longest HTML tag name is 10 characters (BLOCKQUOTE and FIGCAPTION), but SVG tag names can be longer
				// (e.g. feComponentTransfer). This maxLength accounts for possible Custom Elements that are even longer,
				// although the longest known Custom Element from HTTP Archive is 32 characters. See data from <https://almanac.httparchive.org/en/2024/markup#fig-18>.
				'maxLength' => 100,
				'pattern'   => '^[a-zA-Z0-9\-]+\z', // Technically emoji can be allowed in a custom element's tag name, but this is not supported here.
			),
			'id'    => array(
				'type'      => array( 'string', 'null' ),
				'maxLength' => 100, // A reasonable upper-bound length for a long ID.
				'required'  => true,
			),
			'class' => array(
				'type'      => array( 'string', 'null' ),
				'maxLength' => 500, // There can be a ton of class names on an element.
				'required'  => true,
			),
		),
	);
	return $additional_properties;
}

/**
 * Validates URL for a background image.
 *
 * @since 0.3.0
 * @access private
 *
 * @param string $url Background image URL.
 * @return true|WP_Error Validity.
 */
function image_prioritizer_validate_background_image_url( string $url ) {
	$parsed_url = wp_parse_url( $url );
	if ( false === $parsed_url || ! isset( $parsed_url['host'] ) ) {
		return new WP_Error(
			'background_image_url_lacks_host',
			__( 'Supplied background image URL does not have a host.', 'image-prioritizer' )
		);
	}

	$allowed_hosts = array_map(
		static function ( $host ) {
			return wp_parse_url( $host, PHP_URL_HOST );
		},
		get_allowed_http_origins()
	);

	// Obtain the host of an image attachment's URL in case a CDN is pointing all images to an origin other than the home or site URLs.
	$image_attachment_query = new WP_Query(
		array(
			'post_type'              => 'attachment',
			'post_mime_type'         => 'image',
			'post_status'            => 'inherit',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false, // Note that update_post_meta_cache is not included as well because wp_get_attachment_image_src() needs postmeta.
		)
	);
	if ( isset( $image_attachment_query->posts[0] ) && is_int( $image_attachment_query->posts[0] ) ) {
		$src = wp_get_attachment_image_src( $image_attachment_query->posts[0] );
		if ( is_array( $src ) ) {
			$attachment_image_src_host = wp_parse_url( $src[0], PHP_URL_HOST );
			if ( is_string( $attachment_image_src_host ) ) {
				$allowed_hosts[] = $attachment_image_src_host;
			}
		}
	}

	// Validate that the background image URL is for an allowed host.
	if ( ! in_array( $parsed_url['host'], $allowed_hosts, true ) ) {
		return new WP_Error(
			'disallowed_background_image_url_host',
			sprintf(
				/* translators: %s is the list of allowed hosts */
				__( 'Background image URL host is not among allowed: %s.', 'image-prioritizer' ),
				join( ', ', array_unique( $allowed_hosts ) )
			)
		);
	}

	// Validate that the URL points to a valid resource.
	$r = wp_safe_remote_head(
		$url,
		array(
			'redirection' => 3, // Allow up to 3 redirects.
		)
	);
	if ( $r instanceof WP_Error ) {
		return $r;
	}
	$response_code = wp_remote_retrieve_response_code( $r );
	if ( $response_code < 200 || $response_code >= 400 ) {
		return new WP_Error(
			'background_image_response_not_ok',
			sprintf(
				/* translators: %s is the HTTP status code */
				__( 'HEAD request for background image URL did not return with a success status code: %s.', 'image-prioritizer' ),
				$response_code
			)
		);
	}

	// Validate that the Content-Type is an image.
	$content_type = (array) wp_remote_retrieve_header( $r, 'content-type' );
	if ( ! is_string( $content_type[0] ) || ! str_starts_with( $content_type[0], 'image/' ) ) {
		return new WP_Error(
			'background_image_response_not_image',
			sprintf(
				/* translators: %s is the content type of the response */
				__( 'HEAD request for background image URL did not return an image Content-Type: %s.', 'image-prioritizer' ),
				$content_type[0]
			)
		);
	}

	/*
	 * Validate that the Content-Length is not too massive, as it would be better to err on the side of
	 * not preloading something so weighty in case the image won't actually end up as LCP.
	 * The value of 2MB is chosen because according to Web Almanac 2022, the largest image by byte size
	 * on a page is 1MB at the 90th percentile: <https://almanac.httparchive.org/en/2022/media#fig-12>.
	 * The 2MB value is double this 1MB size.
	 */
	$content_length = (array) wp_remote_retrieve_header( $r, 'content-length' );
	if ( ! is_numeric( $content_length[0] ) ) {
		return new WP_Error(
			'background_image_content_length_unknown',
			__( 'HEAD request for background image URL did not include a Content-Length response header.', 'image-prioritizer' )
		);
	} elseif ( (int) $content_length[0] > 2 * MB_IN_BYTES ) {
		return new WP_Error(
			'background_image_content_length_too_large',
			sprintf(
				/* translators: %s is the content length of the response  */
				__( 'HEAD request for background image URL returned Content-Length greater than 2MB: %s.', 'image-prioritizer' ),
				$content_length[0]
			)
		);
	}

	return true;
}

/**
 * Sanitizes the lcpElementExternalBackgroundImage property from the request URL Metric storage request.
 *
 * This removes the lcpElementExternalBackgroundImage from the URL Metric prior to it being stored if the background
 * image URL is not valid. Removal of the property is preferable to invalidating the entire URL Metric because then
 * potentially no URL Metrics would ever be collected if, for example, the background image URL is pointing to a
 * disallowed origin. Then none of the other optimizations would be able to be applied.
 *
 * @since 0.3.0
 * @access private
 *
 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
 *
 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
 *                                                                   Usually a WP_REST_Response or WP_Error.
 * @param array<string, mixed>                             $handler  Route handler used for the request.
 * @param WP_REST_Request                                  $request  Request used to generate the response.
 *
 * @return WP_REST_Response|WP_HTTP_Response|WP_Error|mixed Result to send to the client.
 * @noinspection PhpDocMissingThrowsInspection
 * @noinspection PhpDeprecationInspection
 */
function image_prioritizer_filter_rest_request_before_callbacks( $response, array $handler, WP_REST_Request $request ) {
	unset( $handler ); // Unused.

	// Check for class existence and use constant or class method calls accordingly.
	$route_endpoint = class_exists( 'OD_REST_URL_Metrics_Store_Endpoint' )
						? OD_REST_URL_Metrics_Store_Endpoint::ROUTE_NAMESPACE . OD_REST_URL_Metrics_Store_Endpoint::ROUTE_BASE
						: OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE; // @phpstan-ignore constant.deprecated, constant.deprecated (To be replaced with class method calls in subsequent release.)

	if (
		$request->get_method() !== 'POST'
		||
		// The strtolower() and outer trim are due to \WP_REST_Server::match_request_to_handler() using case-insensitive pattern match and using '$' instead of '\z'.
		( rtrim( strtolower( ltrim( $request->get_route(), '/' ) ) ) !== $route_endpoint )
	) {
		return $response;
	}

	$lcp_external_background_image = $request['lcpElementExternalBackgroundImage'];
	if ( is_array( $lcp_external_background_image ) && isset( $lcp_external_background_image['url'] ) && is_string( $lcp_external_background_image['url'] ) ) {
		$image_validity = image_prioritizer_validate_background_image_url( $lcp_external_background_image['url'] );
		if ( is_wp_error( $image_validity ) ) {
			/**
			 * No WP_Exception is thrown by wp_trigger_error() since E_USER_ERROR is not passed as the error level.
			 *
			 * @noinspection PhpUnhandledExceptionInspection
			 */
			wp_trigger_error(
				__FUNCTION__,
				sprintf(
					/* translators: 1: error message. 2: image url */
					__( 'Error: %1$s. Background image URL: %2$s.', 'image-prioritizer' ),
					rtrim( $image_validity->get_error_message(), '.' ),
					$lcp_external_background_image['url']
				)
			);
			unset( $request['lcpElementExternalBackgroundImage'] );
		}
	}

	return $response;
}

/**
 * Gets the path to a script or stylesheet.
 *
 * @since 0.3.0
 * @access private
 *
 * @param string      $src_path Source path, relative to plugin root.
 * @param string|null $min_path Minified path. If not supplied, then '.min' is injected before the file extension in the source path.
 * @return string URL to script or stylesheet.
 * @noinspection PhpDocMissingThrowsInspection
 */
function image_prioritizer_get_asset_path( string $src_path, ?string $min_path = null ): string {
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
				__( 'Minified asset has not been built: %s', 'image-prioritizer' ),
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

/**
 * Gets the script to lazy-load videos.
 *
 * Load a video and its poster image when it approaches the viewport using an IntersectionObserver.
 *
 * Handles 'autoplay' and 'preload' attributes accordingly.
 *
 * @since 0.2.0
 * @access private
 *
 * @return string Lazy load script.
 */
function image_prioritizer_get_video_lazy_load_script(): string {
	$path = image_prioritizer_get_asset_path( 'lazy-load-video.js' );
	return (string) file_get_contents( __DIR__ . '/' . $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.
}

/**
 * Gets the script to lazy-load background images.
 *
 * Load the background image when it approaches the viewport using an IntersectionObserver.
 *
 * @since 0.3.0
 * @access private
 *
 * @return string Lazy load script.
 */
function image_prioritizer_get_lazy_load_bg_image_script(): string {
	$path = image_prioritizer_get_asset_path( 'lazy-load-bg-image.js' );
	return (string) file_get_contents( __DIR__ . '/' . $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.
}

/**
 * Gets the stylesheet to lazy-load background images.
 *
 * @since 0.3.0
 * @access private
 *
 * @return string Lazy load stylesheet.
 */
function image_prioritizer_get_lazy_load_bg_image_stylesheet(): string {
	$path = image_prioritizer_get_asset_path( 'lazy-load-bg-image.css' );
	return (string) file_get_contents( __DIR__ . '/' . $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.
}
