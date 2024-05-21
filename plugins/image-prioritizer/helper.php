<?php
/**
 * Helper functions for Image Prioritizer.
 *
 * @package image-prioritizer
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Displays the HTML generator meta tag for the Image Prioritizer plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since n.e.x.t
 */
function ip_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="image-prioritizer ' . esc_attr( IMAGE_PRIORITIZER_VERSION ) . '">' . "\n";
}

/**
 * Determines if the provided URL is a data: URL.
 *
 * @param string $url URL.
 * @return bool Whether data URL.
 */
function ip_is_data_url( string $url ): bool {
	return str_starts_with( strtolower( $url ), 'data:' );
}

/**
 * Filters the tag walker visitors which apply image prioritizer optimizations.
 *
 * @since 0.1.0
 * @todo Refactor this into a class.
 *
 * @param callable[]|mixed                $visitors         Visitors which are invoked for each tag in the document.
 * @param OD_HTML_Tag_Walker              $walker           HTML tag walker.
 * @param OD_URL_Metrics_Group_Collection $group_collection URL metrics group collection.
 * @param OD_Preload_Link_Collection      $preload_links    Preload link collection.
 *
 * @return callable[] Visitors.
 */
function ip_filter_tag_walker_visitors( $visitors, OD_HTML_Tag_Walker $walker, OD_URL_Metrics_Group_Collection $group_collection, OD_Preload_Link_Collection $preload_links ): array {
	if ( ! is_array( $visitors ) ) {
		$visitors = array();
	}

	// Capture all the XPaths for known LCP elements.
	$groups_by_lcp_element_xpath   = array();
	$group_has_unknown_lcp_element = false;
	foreach ( $group_collection as $group ) {
		$lcp_element = $group->get_lcp_element();
		if ( null !== $lcp_element ) {
			$groups_by_lcp_element_xpath[ $lcp_element['xpath'] ][] = $group;
		} else {
			$group_has_unknown_lcp_element = true;
		}
	}

	// Prepare to set fetchpriority attribute on the image when all breakpoints have the same LCP element.
	if (
		// All breakpoints share the same LCP element (or all have none at all).
		1 === count( $groups_by_lcp_element_xpath )
		&&
		// The breakpoints don't share a common lack of a detected LCP element.
		! $group_has_unknown_lcp_element
		&&
		// All breakpoints have URL metrics being reported.
		$group_collection->is_every_group_populated()
	) {
		$common_lcp_xpath = key( $groups_by_lcp_element_xpath );
	} else {
		$common_lcp_xpath = null;
	}

	$visitors[] = static function () use ( $walker, $common_lcp_xpath, $group_collection, $groups_by_lcp_element_xpath, $preload_links ): bool {

		$src = trim( (string) $walker->get_attribute( 'src' ) );

		$is_img_tag = (
			'IMG' === $walker->get_tag()
			&&
			$src
			&&
			! ip_is_data_url( $src )
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
			! ip_is_data_url( $matches['background_image'] )
		) {
			$background_image_url = $matches['background_image'];
		}

		if ( ! ( $is_img_tag || $background_image_url ) ) {
			return false;
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
			} elseif ( $walker->get_attribute( 'fetchpriority' ) && $group_collection->is_every_group_populated() ) {
				// Note: The $all_breakpoints_have_url_metrics condition here allows for server-side heuristics to
				// continue to apply while waiting for all breakpoints to have metrics collected for them.
				$walker->set_attribute( 'data-od-removed-fetchpriority', $walker->get_attribute( 'fetchpriority' ) );
				$walker->remove_attribute( 'fetchpriority' );
			}
		}

		// TODO: If the image is visible (intersectionRatio!=0) in any of the URL metrics, remove loading=lazy.
		// TODO: Conversely, if an image is the LCP element for one breakpoint but not another, add loading=lazy. This won't hurt performance since the image is being preloaded.

		// If this element is the LCP (for a breakpoint group), add a preload link for it.
		if ( array_key_exists( $xpath, $groups_by_lcp_element_xpath ) ) {
			foreach ( $groups_by_lcp_element_xpath[ $xpath ] as $group ) {
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

				$preload_links->add_link(
					$link_attributes,
					$group->get_minimum_viewport_width(),
					$group->get_maximum_viewport_width()
				);
			}
		}

		return true;
	};

	return $visitors;
}
