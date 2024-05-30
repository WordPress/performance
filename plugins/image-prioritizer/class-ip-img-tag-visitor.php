<?php
/**
 * Image Prioritizer: IP_Img_Tag_Visitor class
 *
 * @package image-prioritizer
 * @since n.e.x.t
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Visitor for the tag walker that optimizes IMG tags.
 *
 * @since n.e.x.t
 * @access private
 */
final class IP_Img_Tag_Visitor extends IP_Tag_Visitor {

	/**
	 * Visits a tag.
	 *
	 * @param OD_HTML_Tag_Walker $walker Walker.
	 * @return bool Whether the visitor visited the tag.
	 */
	public function __invoke( OD_HTML_Tag_Walker $walker ): bool {
		if ( 'IMG' !== $walker->get_tag() ) {
			return false;
		}

		// Skip empty src attributes and data: URLs.
		$src = trim( (string) $walker->get_attribute( 'src' ) );
		if ( '' === $src || $this->is_data_url( $src ) ) {
			return false;
		}

		$xpath = $walker->get_xpath();

		/*
		 * When the same LCP element is common/shared among all viewport groups, make sure that the element has
		 * fetchpriority=high, even though it won't really be needed because a preload link with fetchpriority=high
		 * will also be added. Additionally, ensure that this common LCP element is never lazy-loaded.
		 */
		$common_lcp_element = $this->url_metrics_group_collection->get_common_lcp_element();
		if ( ! is_null( $common_lcp_element ) && $xpath === $common_lcp_element['xpath'] ) {
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
		} elseif ( is_string( $walker->get_attribute( 'fetchpriority' ) ) && $this->url_metrics_group_collection->is_every_group_populated() ) {
			/*
			 * At this point, the element is not the shared LCP across all viewport groups. It may not be an LCP element
			 * in _any_ of the viewport groups. Nevertheless, server-side heuristics may have added the fetchpriority=high
			 * attribute to the element for some reason. Because of server-side heuristics, we'll only go ahead and remove
			 * the fetchpriority attribute if every viewport group (is_every_group_populated) has been populated with URL
			 * metrics because only then do we know that in fact it is _not_ the LCP element in any of the viewport groups.
			 * This allows for server-side heuristics to continue to apply while waiting for more URL metrics to be gathered.
			 * Note also that if this is the LCP element for _some_ of the viewport groups, it will still get
			 * fetchpriority=high by means of the preload link (with a media query) that is added further below.
			 */
			$walker->set_attribute( 'data-od-removed-fetchpriority', $walker->get_attribute( 'fetchpriority' ) );
			$walker->remove_attribute( 'fetchpriority' );
		}

		// TODO: If the image is visible (intersectionRatio!=0) in any of the URL metrics, remove loading=lazy.
		// TODO: Conversely, if an image is the LCP element for one breakpoint but not another, add loading=lazy. This won't hurt performance since the image is being preloaded.

		// If this element is the LCP (for a breakpoint group), add a preload link for it.
		foreach ( $this->url_metrics_group_collection->get_groups_by_lcp_element( $xpath ) as $group ) {
			$link_attributes = array_merge(
				array(
					'fetchpriority' => 'high',
					'as'            => 'image',
				),
				array_filter(
					array(
						'href'        => (string) $walker->get_attribute( 'src' ),
						'imagesrcset' => (string) $walker->get_attribute( 'srcset' ),
						'imagesizes'  => (string) $walker->get_attribute( 'sizes' ),
					),
					static function ( string $value ): bool {
						return '' !== $value;
					}
				)
			);

			$crossorigin = $walker->get_attribute( 'crossorigin' );
			if ( is_string( $crossorigin ) ) {
				$link_attributes['crossorigin'] = 'use-credentials' === $crossorigin ? 'use-credentials' : 'anonymous';
			}

			$this->preload_links_collection->add_link(
				$link_attributes,
				$group->get_minimum_viewport_width(),
				$group->get_maximum_viewport_width()
			);
		}

		return true;
	}
}
