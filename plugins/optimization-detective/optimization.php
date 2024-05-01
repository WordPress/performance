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
	if ( ! od_can_optimize_response() ) {
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
 * Constructs preload links.
 *
 * @since 0.1.0
 * @access private
 *
 * @param array<int, array{background_image?: string, img_attributes?: array{src?: string, srcset?: string, sizes?: string, crossorigin?: string}}|false> $lcp_elements_by_minimum_viewport_widths LCP elements keyed by minimum viewport width, amended with element details.
 * @return string Markup for zero or more preload link tags.
 */
function od_construct_preload_links( array $lcp_elements_by_minimum_viewport_widths ): string {
	$preload_links = array();

	// This uses a for loop to be able to access the following element within the iteration, using a numeric index.
	$minimum_viewport_widths = array_keys( $lcp_elements_by_minimum_viewport_widths );
	for ( $i = 0, $len = count( $minimum_viewport_widths ); $i < $len; $i++ ) {
		$lcp_element = $lcp_elements_by_minimum_viewport_widths[ $minimum_viewport_widths[ $i ] ];
		if ( false === $lcp_element ) {
			// No supported LCP element at this breakpoint, so nothing to preload.
			continue;
		}

		$link_attributes = array();

		if ( ! empty( $lcp_element['background_image'] ) ) {
			$link_attributes['href'] = $lcp_element['background_image'];
		} elseif ( ! empty( $lcp_element['img_attributes'] ) ) {
			foreach ( $lcp_element['img_attributes'] as $name => $value ) {
				// Map img attribute name to link attribute name.
				if ( 'srcset' === $name || 'sizes' === $name ) {
					$name = 'image' . $name;
				} elseif ( 'src' === $name ) {
					$name = 'href';
				}
				$link_attributes[ $name ] = $value;
			}
		}

		// Skip constructing a link if it is missing required attributes.
		if ( empty( $link_attributes['href'] ) && empty( $link_attributes['imagesrcset'] ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				esc_html(
					__( 'Attempted to construct preload link without an available href or imagesrcset. Supplied LCP element: ', 'optimization-detective' ) . wp_json_encode( $lcp_element )
				),
				''
			);
			continue;
		}

		// Add media query if it's going to be something other than just `min-width: 0px`.
		$minimum_viewport_width = $minimum_viewport_widths[ $i ];
		$maximum_viewport_width = isset( $minimum_viewport_widths[ $i + 1 ] ) ? $minimum_viewport_widths[ $i + 1 ] - 1 : null;
		$media_features         = array( 'screen' );
		if ( $minimum_viewport_width > 0 ) {
			$media_features[] = sprintf( '(min-width: %dpx)', $minimum_viewport_width );
		}
		if ( null !== $maximum_viewport_width ) {
			$media_features[] = sprintf( '(max-width: %dpx)', $maximum_viewport_width );
		}
		$link_attributes['media'] = implode( ' and ', $media_features );

		// Construct preload link.
		$link_tag = '<link data-od-added-tag rel="preload" fetchpriority="high" as="image"';
		foreach ( $link_attributes as $name => $value ) {
			$link_tag .= sprintf( ' %s="%s"', $name, esc_attr( $value ) );
		}
		$link_tag .= ">\n";

		$preload_links[] = $link_tag;
	}

	return implode( '', $preload_links );
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

	$lcp_elements_by_minimum_viewport_widths = od_get_lcp_elements_by_minimum_viewport_widths( $group_collection );
	$all_breakpoints_have_url_metrics        = $group_collection->is_every_group_populated();

	/**
	 * Optimized lookup of the LCP element viewport widths by XPath.
	 *
	 * @var array<string, int[]> $lcp_element_minimum_viewport_widths_by_xpath
	 */
	$lcp_element_minimum_viewport_widths_by_xpath = array();
	foreach ( $lcp_elements_by_minimum_viewport_widths as $minimum_viewport_width => $lcp_element ) {
		if ( false !== $lcp_element ) {
			$lcp_element_minimum_viewport_widths_by_xpath[ $lcp_element['xpath'] ][] = $minimum_viewport_width;
		}
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
		$common_lcp_element = current( $lcp_elements_by_minimum_viewport_widths );
	} else {
		$common_lcp_element = null;
	}

	/**
	 * Mapping of XPath to true to indicate whether the element was found in the document.
	 *
	 * After processing through the entire document, only the elements which were actually found in the document can get
	 * preload links.
	 *
	 * @var array<string, true> $detected_lcp_element_xpaths
	 */
	$detected_lcp_element_xpaths = array();

	// Walk over all tags in the document and ensure fetchpriority is set/removed, and gather IMG attributes or background-image for preloading.
	$walker = new OD_HTML_Tag_Walker( $buffer );
	foreach ( $walker->open_tags() as $tag_name ) {
		$is_img_tag = (
			'IMG' === $tag_name
			&&
			$walker->get_attribute( 'src' )
			&&
			! str_starts_with( $walker->get_attribute( 'src' ), 'data:' )
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
			preg_match( '/background(-image)?\s*:[^;]*?url\(\s*[\'"]?(?<background_image>.+?)[\'"]?\s*\)/', $style, $matches )
			&&
			! str_starts_with( $matches['background_image'], 'data:' )
		) {
			$background_image_url = $matches['background_image'];
		}

		if ( ! ( $is_img_tag || $background_image_url ) ) {
			continue;
		}

		$xpath = $walker->get_xpath();

		// Ensure the fetchpriority attribute is set on the element properly.
		if ( $is_img_tag ) {
			if ( $common_lcp_element && $xpath === $common_lcp_element['xpath'] ) {
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

		// Capture the attributes from the LCP elements to use in preload links.
		if ( isset( $lcp_element_minimum_viewport_widths_by_xpath[ $xpath ] ) ) {
			$detected_lcp_element_xpaths[ $xpath ] = true;

			if ( $is_img_tag ) {
				$img_attributes = array();
				foreach ( array( 'src', 'srcset', 'sizes', 'crossorigin' ) as $attr_name ) {
					$value = $walker->get_attribute( $attr_name );
					if ( null !== $value ) {
						$img_attributes[ $attr_name ] = $value;
					}
				}
				foreach ( $lcp_element_minimum_viewport_widths_by_xpath[ $xpath ] as $minimum_viewport_width ) {
					$lcp_elements_by_minimum_viewport_widths[ $minimum_viewport_width ]['img_attributes'] = $img_attributes;
				}
			} elseif ( $background_image_url ) {
				foreach ( $lcp_element_minimum_viewport_widths_by_xpath[ $xpath ] as $minimum_viewport_width ) {
					$lcp_elements_by_minimum_viewport_widths[ $minimum_viewport_width ]['background_image'] = $background_image_url;
				}
			}
		}

		if ( $needs_detection ) {
			$walker->set_attribute( 'data-od-xpath', $xpath );
		}
	}

	// If there were any LCP elements captured in URL Metrics that no longer exist in the document, we need to behave as
	// if they didn't exist in the first place as there is nothing that can be preloaded.
	foreach ( array_keys( $lcp_element_minimum_viewport_widths_by_xpath ) as $xpath ) {
		if ( empty( $detected_lcp_element_xpaths[ $xpath ] ) ) {
			foreach ( $lcp_element_minimum_viewport_widths_by_xpath[ $xpath ] as $minimum_viewport_width ) {
				$lcp_elements_by_minimum_viewport_widths[ $minimum_viewport_width ] = false;
			}
		}
	}

	// Inject any preload links at the end of the HEAD.
	$head_injection = od_construct_preload_links( $lcp_elements_by_minimum_viewport_widths );
	if ( $head_injection ) {
		$walker->append_head_html( $head_injection );
	}

	// Inject detection script.
	// TODO: When optimizing above, if we find that there is a stored LCP element but it fails to match, it should perhaps set $needs_detection to true and send the request with an override nonce. However, this would require backtracking and adding the data-od-xpath attributes.
	if ( $needs_detection ) {
		$walker->append_body_html( od_get_detection_script( $slug, $group_collection ) );
	}

	return $walker->get_updated_html();
}
