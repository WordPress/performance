<?php
/**
 * Optimizing for image loading optimization.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-ilo-html-tag-processor.php';

/**
 * Adds template output buffer filter for optimization if eligible.
 */
function ilo_maybe_add_template_output_buffer_filter() {
	if ( ! ilo_can_optimize_response() ) {
		return;
	}
	add_filter( 'ilo_template_output_buffer', 'ilo_optimize_template_output_buffer' );
}
add_action( 'wp', 'ilo_maybe_add_template_output_buffer_filter' );

/**
 * Constructs preload links.
 *
 * @param array $lcp_images_by_minimum_viewport_widths LCP images keyed by minimum viewport width, amended with attributes key for the IMG attributes.
 * @return string Markup for one or more preload link tags.
 */
function ilo_construct_preload_links( array $lcp_images_by_minimum_viewport_widths ): string {
	$preload_links = array();

	$minimum_viewport_widths = array_keys( $lcp_images_by_minimum_viewport_widths );
	for ( $i = 0, $len = count( $minimum_viewport_widths ); $i < $len; $i++ ) {
		$lcp_element = $lcp_images_by_minimum_viewport_widths[ $minimum_viewport_widths[ $i ] ];
		if ( false === $lcp_element || empty( $lcp_element['attributes'] ) ) {
			// No LCP element at this breakpoint, so nothing to preload.
			continue;
		}

		$img_attributes = $lcp_element['attributes'];

		// Prevent preloading src for browsers that don't support imagesrcset on the link element.
		if ( isset( $img_attributes['src'], $img_attributes['srcset'] ) ) {
			unset( $img_attributes['src'] );
		}

		// Add media query.
		$media_query = sprintf( 'screen and ( min-width: %dpx )', $minimum_viewport_widths[ $i ] );
		if ( isset( $minimum_viewport_widths[ $i + 1 ] ) ) {
			$media_query .= sprintf( ' and ( max-width: %dpx )', $minimum_viewport_widths[ $i + 1 ] - 1 );
		}
		$img_attributes['media'] = $media_query;

		// Construct preload link.
		$link_tag = '<link data-ilo-added-tag rel="preload" fetchpriority="high" as="image"';
		foreach ( array_filter( $img_attributes ) as $name => $value ) {
			// Map img attribute name to link attribute name.
			if ( 'srcset' === $name || 'sizes' === $name ) {
				$name = 'image' . $name;
			} elseif ( 'src' === $name ) {
				$name = 'href';
			}

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
 * @param string $buffer Template output buffer.
 * @return string Filtered template output buffer.
 */
function ilo_optimize_template_output_buffer( string $buffer ): string {
	$slug = ilo_get_url_metrics_slug( ilo_get_normalized_query_vars() );
	$post = ilo_get_url_metrics_post( $slug );

	// No URL metrics are present, so there's nothing we can do.
	if ( ! $post ) {
		return $buffer;
	}

	$url_metrics = ilo_parse_stored_url_metrics( $post );

	$breakpoint_max_widths                   = ilo_get_breakpoint_max_widths();
	$url_metrics_grouped_by_breakpoint       = ilo_group_url_metrics_by_breakpoint( $url_metrics, $breakpoint_max_widths );
	$lcp_elements_by_minimum_viewport_widths = ilo_get_lcp_elements_by_minimum_viewport_widths( $url_metrics_grouped_by_breakpoint );

	// TODO: Handle case when the LCP element is not an image at all, but rather a background-image.
	// Use the fetchpriority attribute on the image when all breakpoints have the same LCP element.
	if (
		// All breakpoints share the same LCP element (or all have none at all).
		1 === count( $lcp_elements_by_minimum_viewport_widths )
		&&
		// The breakpoints don't share a common lack of an LCP element.
		! in_array( false, $lcp_elements_by_minimum_viewport_widths, true )
		&&
		// All breakpoints have URL metrics being reported.
		count( array_filter( $url_metrics_grouped_by_breakpoint ) ) === count( $breakpoint_max_widths ) + 1
	) {
		$lcp_element = current( $lcp_elements_by_minimum_viewport_widths );

		$processor = new ILO_HTML_Tag_Processor( $buffer );
		$processor->walk(
			static function () use ( $processor, $lcp_element ) {
				if ( $processor->get_tag() !== 'IMG' ) {
					return;
				}

				if ( $processor->get_breadcrumbs() === $lcp_element['breadcrumbs'] ) {
					if ( 'high' === $processor->get_attribute( 'fetchpriority' ) ) {
						$processor->set_attribute( 'data-ilo-fetchpriority-already-added', true );
					} else {
						$processor->set_attribute( 'fetchpriority', 'high' );
						$processor->set_attribute( 'data-ilo-added-fetchpriority', true );
					}
				} else {
					$processor->remove_fetchpriority_attribute();
				}
			}
		);
		$buffer = $processor->get_updated_html();

		// TODO: We could also add the preload links here.
	} else {
		// If there is not exactly one LCP element, we need to remove fetchpriority from all images while also
		// capturing the attributes from the LCP element which we can then use for preload links.
		$processor = new ILO_HTML_Tag_Processor( $buffer );
		$processor->walk(
			static function () use ( $processor, &$lcp_elements_by_minimum_viewport_widths ) {
				if ( $processor->get_tag() !== 'IMG' ) {
					return;
				}

				$processor->remove_fetchpriority_attribute();

				// Capture the attributes from the LCP elements to use in preload links.
				foreach ( $lcp_elements_by_minimum_viewport_widths as &$lcp_element ) {
					if ( $lcp_element && $lcp_element['breadcrumbs'] === $processor->get_breadcrumbs() ) {
						$lcp_element['attributes'] = array();
						foreach ( array( 'src', 'srcset', 'sizes', 'crossorigin', 'integrity' ) as $attr_name ) {
							$lcp_element['attributes'][ $attr_name ] = $processor->get_attribute( $attr_name );
						}
					}
				}
			}
		);
		$buffer = $processor->get_updated_html();

		$preload_links = ilo_construct_preload_links( $lcp_elements_by_minimum_viewport_widths );

		// TODO: In the future, WP_HTML_Processor could be used to do this injection. However, given the simple replacement here this is not essential.
		$buffer = preg_replace( '#(?=</HEAD>)#i', $preload_links, $buffer, 1 );
	}

	return $buffer;
}
