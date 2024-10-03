<?php
/**
 * Image Prioritizer: IP_Img_Tag_Visitor class
 *
 * @package image-prioritizer
 * @since 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tag visitor that optimizes IMG tags.
 *
 * @since 0.1.0
 * @access private
 */
final class Image_Prioritizer_Img_Tag_Visitor extends Image_Prioritizer_Tag_Visitor {

	/**
	 * Visits a tag.
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context.
	 *
	 * @return bool Whether the tag should be tracked in URL metrics.
	 */
	public function __invoke( OD_Tag_Visitor_Context $context ): bool {
		$processor = $context->processor;
		if ( 'IMG' !== $processor->get_tag() ) {
			return false;
		}

		// Skip empty src attributes and data: URLs.
		$src = trim( (string) $processor->get_attribute( 'src' ) );
		if ( '' === $src || $this->is_data_url( $src ) ) {
			return false;
		}

		$xpath = $processor->get_xpath();

		/**
		 * Gets attribute value.
		 *
		 * @param string $attribute_name Attribute name.
		 * @return string|true|null Normalized attribute value.
		 */
		$get_attribute_value = static function ( string $attribute_name ) use ( $processor ) {
			$value = $processor->get_attribute( $attribute_name );
			if ( is_string( $value ) ) {
				$value = strtolower( trim( $value, " \t\f\r\n" ) );
			}
			return $value;
		};

		/*
		 * When the same LCP element is common/shared among all viewport groups, make sure that the element has
		 * fetchpriority=high, even though it won't really be needed because a preload link with fetchpriority=high
		 * will also be added. Additionally, ensure that this common LCP element is never lazy-loaded.
		 */
		$common_lcp_element = $context->url_metric_group_collection->get_common_lcp_element();
		if ( ! is_null( $common_lcp_element ) && $xpath === $common_lcp_element['xpath'] ) {
			if ( 'high' === $get_attribute_value( 'fetchpriority' ) ) {
				$processor->set_meta_attribute( 'fetchpriority-already-added', true );
			} else {
				$processor->set_attribute( 'fetchpriority', 'high' );
			}
		} elseif (
			is_string( $processor->get_attribute( 'fetchpriority' ) )
			&&
			// Temporary condition in case someone updates Image Prioritizer without also updating Optimization Detective.
			method_exists( $context->url_metric_group_collection, 'is_any_group_populated' )
			&&
			$context->url_metric_group_collection->is_any_group_populated()
		) {
			/*
			 * At this point, the element is not the shared LCP across all viewport groups. Nevertheless, server-side
			 * heuristics have added fetchpriority=high to the element, but this is not warranted either due to a lack
			 * of data or because the LCP element is not common across all viewport groups. Since we have collected at
			 * least some URL metrics (per is_any_group_populated), further below a fetchpriority=high preload link will
			 * be added for the viewport(s) for which this is actually the LCP element. Some viewport groups may never
			 * get populated due to a lack of traffic (e.g. from tablets or phablets), so it is important to remove
			 * fetchpriority=high in such case to prevent server-side heuristics from prioritizing loading the image
			 * which isn't actually the LCP element for actual visitors.
			 */
			$processor->remove_attribute( 'fetchpriority' );
		}

		$element_max_intersection_ratio = $context->url_metric_group_collection->get_element_max_intersection_ratio( $xpath );

		// If the element was not found, we don't know if it was visible for not, so don't do anything.
		if ( is_null( $element_max_intersection_ratio ) ) {
			$processor->set_meta_attribute( 'unknown-tag', true ); // Mostly useful for debugging why an IMG isn't optimized.
		} else {
			// Otherwise, make sure visible elements omit the loading attribute, and hidden elements include loading=lazy.
			$is_visible = $element_max_intersection_ratio > 0.0;
			$loading    = $get_attribute_value( 'loading' );
			if ( $is_visible && 'lazy' === $loading ) {
				$processor->remove_attribute( 'loading' );
			} elseif ( ! $is_visible && 'lazy' !== $loading ) {
				$processor->set_attribute( 'loading', 'lazy' );
			}
		}
		// TODO: If an image is visible in one breakpoint but not another, add loading=lazy AND add a regular-priority preload link with media queries (unless LCP in which case it should already have a fetchpriority=high link) so that the image won't be eagerly-loaded for viewports on which it is not shown.

		// Ensure that sizes=auto is set properly.
		$sizes = $processor->get_attribute( 'sizes' );
		if ( is_string( $sizes ) ) {
			$is_lazy  = 'lazy' === $get_attribute_value( 'loading' );
			$has_auto = $this->sizes_attribute_includes_valid_auto( $sizes );

			if ( $is_lazy && ! $has_auto ) {
				$processor->set_attribute( 'sizes', "auto, $sizes" );
			} elseif ( ! $is_lazy && $has_auto ) {
				// Remove auto from the beginning of the list.
				$processor->set_attribute(
					'sizes',
					(string) preg_replace( '/^[ \t\f\r\n]*auto[ \t\f\r\n]*(,[ \t\f\r\n]*)?/i', '', $sizes )
				);
			}
		}

		// If this element is the LCP (for a breakpoint group), add a preload link for it.
		foreach ( $context->url_metric_group_collection->get_groups_by_lcp_element( $xpath ) as $group ) {
			$link_attributes = array_merge(
				array(
					'rel'           => 'preload',
					'fetchpriority' => 'high',
					'as'            => 'image',
				),
				array_filter(
					array(
						'href'        => (string) $processor->get_attribute( 'src' ),
						'imagesrcset' => (string) $processor->get_attribute( 'srcset' ),
						'imagesizes'  => (string) $processor->get_attribute( 'sizes' ),
					),
					static function ( string $value ): bool {
						return '' !== $value;
					}
				)
			);

			$crossorigin = $get_attribute_value( 'crossorigin' );
			if ( null !== $crossorigin ) {
				$link_attributes['crossorigin'] = 'use-credentials' === $crossorigin ? 'use-credentials' : 'anonymous';
			}

			$link_attributes['media'] = 'screen';

			$context->link_collection->add_link(
				$link_attributes,
				$group->get_minimum_viewport_width(),
				$group->get_maximum_viewport_width()
			);
		}

		return true;
	}

	/**
	 * Checks whether the given 'sizes' attribute includes the 'auto' keyword as the first item in the list.
	 *
	 * Per the HTML spec, if present it must be the first entry.
	 *
	 * @since 0.1.4
	 *
	 * @param string $sizes_attr The 'sizes' attribute value.
	 * @return bool True if the 'auto' keyword is present, false otherwise.
	 */
	private function sizes_attribute_includes_valid_auto( string $sizes_attr ): bool {
		if ( function_exists( 'wp_sizes_attribute_includes_valid_auto' ) ) {
			return wp_sizes_attribute_includes_valid_auto( $sizes_attr );
		} elseif ( function_exists( 'auto_sizes_attribute_includes_valid_auto' ) ) {
			return auto_sizes_attribute_includes_valid_auto( $sizes_attr );
		} else {
			return 'auto' === $sizes_attr || str_starts_with( $sizes_attr, 'auto,' );
		}
	}
}
