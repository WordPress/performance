<?php
/**
 * Optimizing for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Starts output buffering at the end of the 'template_include' filter.
 *
 * This is to implement #43258 in core.
 *
 * This is a hack which would eventually be replaced with something like this in wp-includes/template-loader.php:
 *
 *          $template = apply_filters( 'template_include', $template );
 *     +    ob_start( 'wp_template_output_buffer_callback' );
 *          if ( $template ) {
 *              include $template;
 *          } elseif ( current_user_can( 'switch_themes' ) ) {
 *
 * @since 0.1.0
 * @access private
 * @link https://core.trac.wordpress.org/ticket/43258
 *
 * @param string $passthrough Value for the template_include filter which is passed through.
 * @return string Unmodified value of $passthrough.
 */
function od_buffer_output( string $passthrough ): string {
	ob_start(
		static function ( string $output ): string {
			/**
			 * Filters the template output buffer prior to sending to the client.
			 *
			 * @since 0.1.0
			 *
			 * @param string $output Output buffer.
			 * @return string Filtered output buffer.
			 */
			return (string) apply_filters( 'od_template_output_buffer', $output );
		}
	);
	return $passthrough;
}

/**
 * Adds template output buffer filter for optimization if eligible.
 *
 * @since 0.1.0
 * @access private
 */
function od_maybe_add_template_output_buffer_filter(): void {
	if ( ! od_can_optimize_response() || isset( $_GET['optimization_detective_disabled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$callback = 'od_optimize_template_output_buffer';
	if (
		function_exists( 'perflab_wrap_server_timing' )
		&&
		function_exists( 'perflab_server_timing_use_output_buffer' )
		&&
		perflab_server_timing_use_output_buffer()
	) {
		$callback = perflab_wrap_server_timing( $callback, 'optimization-detective', 'exist' );
	}
	add_filter( 'od_template_output_buffer', $callback );
}

/**
 * Determines whether the current response can be optimized.
 *
 * @since 0.1.0
 * @access private
 *
 * @return bool Whether response can be optimized.
 */
function od_can_optimize_response(): bool {
	$able = ! (
		// Since there is no predictability in whether posts in the loop will have featured images assigned or not. If a
		// theme template for search results doesn't even show featured images, then this wouldn't be an issue.
		is_search() ||
		// Since injection of inline-editing controls interfere with breadcrumbs, while also just not necessary in this context.
		is_customize_preview() ||
		// Since the images detected in the response body of a POST request cannot, by definition, be cached.
		( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) ||
		// The aim is to optimize pages for the majority of site visitors, not those who administer the site. For admin
		// users, additional elements will be present like the script from wp_customize_support_script() which will
		// interfere with the XPath indices. Note that od_get_normalized_query_vars() is varied by is_user_logged_in()
		// so membership sites and e-commerce sites will still be able to be optimized for their normal visitors.
		current_user_can( 'customize' )
	);

	/**
	 * Filters whether the current response can be optimized.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $able Whether response can be optimized.
	 */
	return (bool) apply_filters( 'od_can_optimize_response', $able );
}

/**
 * Determines whether the response has an HTML Content-Type.
 *
 * @since 0.2.0
 * @private
 *
 * @return bool Whether Content-Type is HTML.
 */
function od_is_response_html_content_type(): bool {
	$is_html_content_type = false;

	$headers_list = array_merge(
		array( 'Content-Type: ' . ini_get( 'default_mimetype' ) ),
		headers_list()
	);
	foreach ( $headers_list as $header ) {
		$header_parts = preg_split( '/\s*[:;]\s*/', strtolower( $header ) );
		if ( is_array( $header_parts ) && count( $header_parts ) >= 2 && 'content-type' === $header_parts[0] ) {
			$is_html_content_type = in_array( $header_parts[1], array( 'text/html', 'application/xhtml+xml' ), true );
		}
	}

	return $is_html_content_type;
}

/**
 * Optimizes template output buffer.
 *
 * @since 0.1.0
 * @access private
 *
 * @param string $buffer Template output buffer.
 * @return string Filtered template output buffer.
 */
function od_optimize_template_output_buffer( string $buffer ): string {
	if ( ! od_is_response_html_content_type() ) {
		return $buffer;
	}

	$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
	$post = OD_URL_Metrics_Post_Type::get_post( $slug );

	$group_collection = new OD_URL_Metrics_Group_Collection(
		$post ? OD_URL_Metrics_Post_Type::get_url_metrics_from_post( $post ) : array(),
		od_get_breakpoint_max_widths(),
		od_get_url_metrics_breakpoint_sample_size(),
		od_get_url_metric_freshness_ttl()
	);

	// Whether we need to add the data-od-xpath attribute to elements and whether the detection script should be injected.
	$needs_detection = ! $group_collection->is_every_group_complete();

	// TODO: This should rather get all LCP Elements along with their minimum and maximum viewport widths.
	$lcp_elements_by_minimum_viewport_widths = od_get_lcp_elements_by_minimum_viewport_widths( $group_collection );
	$all_breakpoints_have_url_metrics        = $group_collection->is_every_group_populated();

	// Capture all the XPaths for known LCP elements.
	$lcp_element_xpaths = array();
	foreach ( array_filter( $lcp_elements_by_minimum_viewport_widths ) as $lcp_element ) {
		$lcp_element_xpaths[] = $lcp_element['xpath'];
	}

	// Prepare to set fetchpriority attribute on the image when all breakpoints have the same LCP element.
	if (
		// All breakpoints share the same LCP element (or all have none at all).
		1 === count( $lcp_elements_by_minimum_viewport_widths )
		&&
		// The breakpoints don't share a common lack of an LCP image.
		! in_array( false, $lcp_elements_by_minimum_viewport_widths, true )
		&&
		// All breakpoints have URL metrics being reported.
		$all_breakpoints_have_url_metrics
	) {
		$common_lcp_xpath = current( $lcp_elements_by_minimum_viewport_widths )['xpath'];
	} else {
		$common_lcp_xpath = null;
	}

	$preload_links = new OD_Preload_Link_Collection();

	$is_data_url = static function ( string $url ): bool {
		return strtolower( substr( $url, 0, 5 ) ) === 'data:';
	};

	// Walk over all tags in the document and ensure fetchpriority is set/removed, and construct preload links for image LCP elements.
	$walker = new OD_HTML_Tag_Walker( $buffer );
	foreach ( $walker->open_tags() as $tag_name ) {
		$src = trim( (string) $walker->get_attribute( 'src' ) );

		$is_img_tag = (
			'IMG' === $tag_name
			&&
			$src
			&&
			! $is_data_url( $src )
		);

		/*
		 * Note that CSS allows for a `background`/`background-image` to have multiple `url()` CSS functions, resulting
		 * in multiple background images being layered on top of each other. This ability is not employed in core. Here
		 * is a regex to search WPDirectory for instances of this: /background(-image)?:[^;}]+?url\([^;}]+?[^_]url\(/.
		 * It is used in Jetpack with the second background image being a gradient. To support multiple background
		 * images, this logic would need to be modified to make $background_image an array and to have a more robust
		 * parser of the `url()` functions from the property value.
		 */
		$background_image_url = null;
		$style                = $walker->get_attribute( 'style' );
		if (
			$style
			&&
			preg_match( '/background(-image)?\s*:[^;]*?url\(\s*[\'"]?\s*(?<background_image>.+?)\s*[\'"]?\s*\)/', (string) $style, $matches )
			&&
			! $is_data_url( $matches['background_image'] )
		) {
			$background_image_url = $matches['background_image'];
		}

		if ( ! ( $is_img_tag || $background_image_url ) ) {
			continue;
		}

		$xpath = $walker->get_xpath();

		// Ensure the fetchpriority attribute is set on the element properly.
		if ( $is_img_tag ) {
			if ( $common_lcp_xpath && $xpath === $common_lcp_xpath ) {
				if ( 'high' === $walker->get_attribute( 'fetchpriority' ) ) {
					$walker->set_attribute( 'data-od-fetchpriority-already-added', true );
				} else {
					$walker->set_attribute( 'fetchpriority', 'high' );
					$walker->set_attribute( 'data-od-added-fetchpriority', true );
				}

				// Never include loading=lazy on the LCP image common across all breakpoints.
				if ( 'lazy' === $walker->get_attribute( 'loading' ) ) {
					$walker->set_attribute( 'data-od-removed-loading', $walker->get_attribute( 'loading' ) );
					$walker->remove_attribute( 'loading' );
				}
			} elseif ( $all_breakpoints_have_url_metrics && $walker->get_attribute( 'fetchpriority' ) ) {
				// Note: The $all_breakpoints_have_url_metrics condition here allows for server-side heuristics to
				// continue to apply while waiting for all breakpoints to have metrics collected for them.
				$walker->set_attribute( 'data-od-removed-fetchpriority', $walker->get_attribute( 'fetchpriority' ) );
				$walker->remove_attribute( 'fetchpriority' );
			}
		}

		// TODO: If the image is visible (intersectionRatio!=0) in any of the URL metrics, remove loading=lazy.
		// TODO: Conversely, if an image is the LCP element for one breakpoint but not another, add loading=lazy. This won't hurt performance since the image is being preloaded.

		// If this element is the LCP (for a breakpoint group), add a preload link for it.
		if ( in_array( $xpath, $lcp_element_xpaths, true ) ) {
			$minimum_viewport_widths = array_keys( $lcp_elements_by_minimum_viewport_widths );
			for ( $i = 0, $len = count( $minimum_viewport_widths ); $i < $len; $i++ ) {
				$lcp_element = $lcp_elements_by_minimum_viewport_widths[ $minimum_viewport_widths[ $i ] ];
				if ( false === $lcp_element || $xpath !== $lcp_element['xpath'] ) {
					// This LCP element is not at this breakpoint, so nothing to preload.
					continue;
				}

				$minimum_viewport_width = (int) $minimum_viewport_widths[ $i ];
				$maximum_viewport_width = isset( $minimum_viewport_widths[ $i + 1 ] ) ? (int) $minimum_viewport_widths[ $i + 1 ] - 1 : null;

				$link_attributes = array(
					'fetchpriority' => 'high',
					'as'            => 'image',
				);
				if ( $is_img_tag ) {
					$link_attributes = array_merge(
						$link_attributes,
						array_filter(
							array(
								'href'        => (string) $walker->get_attribute( 'src' ),
								'imagesrcset' => (string) $walker->get_attribute( 'srcset' ),
								'imagesizes'  => (string) $walker->get_attribute( 'sizes' ),
							)
						)
					);

					$crossorigin = $walker->get_attribute( 'crossorigin' );
					if ( $crossorigin ) {
						$link_attributes['crossorigin'] = 'use-credentials' === $crossorigin ? 'use-credentials' : 'anonymous';
					}
				} elseif ( $background_image_url ) {
					$link_attributes['href'] = $background_image_url;
				}

				// TODO: The additional type checks here should not be required if we don't rely on the minimum viewport widths being the array keys above.
				if (
					( $minimum_viewport_width >= 0 ) &&
					( is_null( $maximum_viewport_width ) || $maximum_viewport_width >= 1 )
				) {
					$preload_links->add_link( $link_attributes, $minimum_viewport_width, $maximum_viewport_width );
				}
			}
		}

		if ( $needs_detection ) {
			$walker->set_attribute( 'data-od-xpath', $xpath );
		}
	}

	// Inject any preload links at the end of the HEAD.
	if ( count( $preload_links ) > 0 ) {
		$walker->append_head_html( $preload_links->get_html() );
	}

	// Inject detection script.
	// TODO: When optimizing above, if we find that there is a stored LCP element but it fails to match, it should perhaps set $needs_detection to true and send the request with an override nonce. However, this would require backtracking and adding the data-od-xpath attributes.
	if ( $needs_detection ) {
		$walker->append_body_html( od_get_detection_script( $slug, $group_collection ) );
	}

	return $walker->get_updated_html();
}
