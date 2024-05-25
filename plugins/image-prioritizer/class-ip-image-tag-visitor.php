<?php
/**
 * Image Prioritizer: IP_Image_Tag_Visitor class
 *
 * @package image-prioritizer
 * @since n.e.x.t
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Visitor for the tag walker that optimizes image tags (both `img` tags and elements with background-image styles).
 *
 * @todo Let this be an abstract class that a background-image visitor and an IMG tag visitor both inherit from.
 *
 * @since n.e.x.t
 * @access private
 */
final class IP_Image_Tag_Visitor {

	/**
	 * URL Metrics Group Collection.
	 *
	 * @var OD_URL_Metrics_Group_Collection
	 */
	private $url_metrics_group_collection;

	/**
	 * Preload Link Collection.
	 *
	 * @var OD_Preload_Link_Collection
	 */
	private $preload_links_collection;

	/**
	 * Common LCP element XPath.
	 *
	 * @var string|null
	 */
	private $common_lcp_xpath = null;

	/**
	 * Constructor.
	 *
	 * @param OD_URL_Metrics_Group_Collection $url_metrics_group_collection URL Metrics Group Collection.
	 * @param OD_Preload_Link_Collection      $preload_links_collection     Preload Link Collection.
	 */
	public function __construct( OD_URL_Metrics_Group_Collection $url_metrics_group_collection, OD_Preload_Link_Collection $preload_links_collection ) {
		$this->url_metrics_group_collection = $url_metrics_group_collection;
		$this->preload_links_collection     = $preload_links_collection;

		// Capture all the XPaths for known LCP elements.
		$groups_by_lcp_element_xpath   = array();
		$group_has_unknown_lcp_element = false;
		foreach ( $url_metrics_group_collection as $group ) {
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
			$url_metrics_group_collection->is_every_group_populated()
		) {
			$this->common_lcp_xpath = key( $groups_by_lcp_element_xpath );
		}
	}

	/**
	 * Visits a tag.
	 *
	 * @param OD_HTML_Tag_Walker $walker Walker.
	 * @return bool Whether the visitor visited the tag.
	 */
	public function __invoke( OD_HTML_Tag_Walker $walker ): bool {
		$src = trim( (string) $walker->get_attribute( 'src' ) );

		$is_img_tag = (
			'IMG' === $walker->get_tag()
			&&
			'' !== $src
			&&
			! $this->is_data_url( $src )
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
			is_string( $style )
			&&
			false !== preg_match( '/background(-image)?\s*:[^;]*?url\(\s*[\'"]?\s*(?<background_image>.+?)\s*[\'"]?\s*\)/', $style, $matches )
			&&
			'' !== $matches['background_image'] // PHPStan should ideally know that this is a non-empty string based on the `.+?` regular expression.
			&&
			! $this->is_data_url( $matches['background_image'] )
		) {
			$background_image_url = $matches['background_image'];
		}

		if ( ! $is_img_tag && is_null( $background_image_url ) ) {
			return false;
		}

		$xpath = $walker->get_xpath();

		// Ensure the fetchpriority attribute is set on the element properly.
		if ( $is_img_tag ) {
			if ( ! is_null( $this->common_lcp_xpath ) && $xpath === $this->common_lcp_xpath ) {
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
				// Note: The $all_breakpoints_have_url_metrics condition here allows for server-side heuristics to
				// continue to apply while waiting for all breakpoints to have metrics collected for them.
				$walker->set_attribute( 'data-od-removed-fetchpriority', $walker->get_attribute( 'fetchpriority' ) );
				$walker->remove_attribute( 'fetchpriority' );
			}
		}

		// TODO: If the image is visible (intersectionRatio!=0) in any of the URL metrics, remove loading=lazy.
		// TODO: Conversely, if an image is the LCP element for one breakpoint but not another, add loading=lazy. This won't hurt performance since the image is being preloaded.

		// If this element is the LCP (for a breakpoint group), add a preload link for it.
		foreach ( $this->url_metrics_group_collection->get_groups_by_lcp_element( $xpath ) as $group ) {
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
			} else {
				$link_attributes['href'] = $background_image_url;
			}

			$this->preload_links_collection->add_link(
				$link_attributes,
				$group->get_minimum_viewport_width(),
				$group->get_maximum_viewport_width()
			);
		}

		return true;
	}

	/**
	 * Determines if the provided URL is a data: URL.
	 *
	 * @param string $url URL.
	 * @return bool Whether data URL.
	 */
	private function is_data_url( string $url ): bool {
		return str_starts_with( strtolower( $url ), 'data:' );
	}
}
