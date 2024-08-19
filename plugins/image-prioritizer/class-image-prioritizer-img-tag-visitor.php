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
	 * @return bool Whether the visitor visited the tag.
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

		$current_fetchpriority = strtolower( trim( (string) $processor->get_attribute( 'fetchpriority' ), " \t\f\r\n" ) );
		$is_lazy_loaded        = 'lazy' === strtolower( trim( (string) $processor->get_attribute( 'loading' ), " \t\f\r\n" ) );
		$updated_fetchpriority = null;

		/*
		 * When the same LCP element is common/shared among all viewport groups, make sure that the element has
		 * fetchpriority=high, even though it won't really be needed because a preload link with fetchpriority=high
		 * will also be added. Additionally, ensure that this common LCP element is never lazy-loaded.
		 */
		$common_lcp_element = $context->url_metrics_group_collection->get_common_lcp_element();
		if ( ! is_null( $common_lcp_element ) && $xpath === $common_lcp_element['xpath'] ) {
			$updated_fetchpriority = 'high';
		} elseif (
			'high' === $current_fetchpriority
			&&
			// Temporary condition in case someone updates Image Prioritizer without also updating Optimization Detective.
			method_exists( $context->url_metrics_group_collection, 'is_any_group_populated' )
			&&
			$context->url_metrics_group_collection->is_any_group_populated()
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
			$updated_fetchpriority = false; // That is, remove it.
		}

		$element_max_intersection_ratio        = $context->url_metrics_group_collection->get_element_max_intersection_ratio( $xpath );
		$is_positioned_in_any_initial_viewport = null;
		// Temporary condition in case Optimization Detective is not updated to the latest version yet.
		if ( method_exists( $context->url_metrics_group_collection, 'is_element_positioned_in_any_initial_viewport' ) ) {
			$is_positioned_in_any_initial_viewport = $context->url_metrics_group_collection->is_element_positioned_in_any_initial_viewport( $xpath );
		}

		// If the element was not found, we don't know if it was visible for not, so don't do anything.
		if ( is_null( $element_max_intersection_ratio ) || is_null( $is_positioned_in_any_initial_viewport ) ) {
			$processor->set_meta_attribute( 'unknown-tag', true ); // Mostly useful for debugging why an IMG isn't optimized.
		} else {
			// TODO: Take into account whether the element has the computed style of visibility:hidden, in such case it should also get fetchpriority=low.
			$is_visible = $element_max_intersection_ratio > 0.0;
			if ( $is_positioned_in_any_initial_viewport ) {
				if ( ! $is_visible ) {
					// If an element is positioned in the initial viewport and yet it is it not visible, it may be
					// located in a subsequent carousel slide or inside a hidden navigation menu which could be
					// displayed at any time. Therefore, it should get fetchpriority=low so that any images which are
					// visible can be loaded with a higher priority.
					$updated_fetchpriority = 'low';

					// Also prevent the image from being lazy-loaded (or eager-loaded) since it may be revealed at any
					// time without the browser having any signal (e.g. user scrolling toward it) to start downloading.
					$processor->remove_attribute( 'loading' );
				} elseif ( $is_lazy_loaded ) {
					// Otherwise, if the image is positioned inside any initial viewport then it should never get lazy-loaded.
					$processor->remove_attribute( 'loading' );
				}
			} elseif ( ! $is_lazy_loaded && ! $is_visible ) {
				// Otherwise, the element is not positioned in any initial viewport, so it should always get lazy-loaded.
				// The `! $is_visible` condition should always evaluate to true since the intersectionRatio of an
				// element positioned below the initial viewport should by definition never be visible.
				$processor->set_attribute( 'loading', 'lazy' );
			}
		}
		// TODO: If an image is visible in one breakpoint but not another, add loading=lazy AND add a regular-priority preload link with media queries (unless LCP in which case it should already have a fetchpriority=high link) so that the image won't be eagerly-loaded for viewports on which it is not shown.

		// Set the fetchpriority attribute if needed.
		if ( is_string( $updated_fetchpriority ) && $updated_fetchpriority !== $current_fetchpriority ) {
			$processor->set_attribute( 'fetchpriority', $updated_fetchpriority );
		} elseif ( $updated_fetchpriority === $current_fetchpriority ) {
			$processor->set_meta_attribute( 'fetchpriority-already-added', true );
		} elseif ( false === $updated_fetchpriority ) {
			$processor->remove_attribute( 'fetchpriority' );
		}

		// If this element is the LCP (for a breakpoint group), add a preload link for it.
		foreach ( $context->url_metrics_group_collection->get_groups_by_lcp_element( $xpath ) as $group ) {
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

			$crossorigin = $processor->get_attribute( 'crossorigin' );
			if ( is_string( $crossorigin ) ) {
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
}
