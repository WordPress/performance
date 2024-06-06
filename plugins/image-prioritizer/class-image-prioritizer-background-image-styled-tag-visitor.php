<?php
/**
 * Image Prioritizer: IP_Background_Image_Styled_Tag_Visitor class
 *
 * @package image-prioritizer
 * @since 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Visitor for the tag walker that optimizes elements with background-image styles.
 *
 * @since 0.1.0
 * @access private
 */
final class Image_Prioritizer_Background_Image_Styled_Tag_Visitor extends Image_Prioritizer_Tag_Visitor {

	/**
	 * Visits a tag.
	 *
	 * @param OD_HTML_Tag_Walker $walker Walker.
	 * @return bool Whether the visitor visited the tag.
	 */
	public function __invoke( OD_HTML_Tag_Walker $walker ): bool {
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

		if ( is_null( $background_image_url ) ) {
			return false;
		}

		$xpath = $walker->get_xpath();

		// If this element is the LCP (for a breakpoint group), add a preload link for it.
		foreach ( $this->url_metrics_group_collection->get_groups_by_lcp_element( $xpath ) as $group ) {
			$link_attributes = array(
				'fetchpriority' => 'high',
				'as'            => 'image',
				'href'          => $background_image_url,
			);

			$this->preload_links_collection->add_link(
				$link_attributes,
				$group->get_minimum_viewport_width(),
				$group->get_maximum_viewport_width()
			);
		}

		return true;
	}
}
