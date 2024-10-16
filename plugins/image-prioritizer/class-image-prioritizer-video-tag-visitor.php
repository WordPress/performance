<?php
/**
 * Tag visitor that optimizes VIDEO tags:
 * - Adds preload links for poster images if in a breakpoint group's LCP.
 *
 * @package image-prioritizer
 *
 * @since n.e.x.t
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Prioritizer: Image_Prioritizer_Video_Tag_Visitor class
 *
 * @since n.e.x.t
 *
 * @access private
 */
final class Image_Prioritizer_Video_Tag_Visitor extends Image_Prioritizer_Tag_Visitor {

	/**
	 * Visits a tag.
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context.
	 *
	 * @return bool Whether the tag should be tracked in URL metrics.
	 */
	public function __invoke( OD_Tag_Visitor_Context $context ): bool {
		$processor = $context->processor;
		if ( 'VIDEO' !== $processor->get_tag() ) {
			return false;
		}

		// TODO: If $context->url_metric_group_collection->get_element_max_intersection_ratio( $xpath ) is 0.0, then the video is not in any initial viewport and the VIDEO tag could get the preload=none attribute added.

		$this->reduce_poster_image_size( $context );
		$this->preload_poster_image( $context );

		return true;
	}

	/**
	 * Reduce poster image size by choosing one that fits the maximum video size more closely.
	 *
	 * @since n.e.x.t
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context, with the cursor currently at an embed block.
	 */
	private function reduce_poster_image_size( OD_Tag_Visitor_Context $context ): void {
		$processor = $context->processor;

		// Skip empty poster attributes and data: URLs.
		$poster = trim( (string) $processor->get_attribute( 'poster' ) );
		if ( '' === $poster || $this->is_data_url( $poster ) ) {
			return;
		}

		$xpath = $processor->get_xpath();

		$max_element_width = 0;

		$denormalized_elements = $context->url_metric_group_collection->get_all_denormalized_elements()[ $xpath ] ?? array();

		foreach ( $denormalized_elements as list( , , $element ) ) {
			$max_element_width = max( $max_element_width, $element['boundingClientRect']['width'] ?? 0 );
		}

		$poster_id = attachment_url_to_postid( $poster );

		if ( $poster_id > 0 && $max_element_width > 0 ) {
			$smaller_image_url = wp_get_attachment_image_url( $poster_id, array( (int) $max_element_width, 0 ) );
			$processor->set_attribute( 'poster', $smaller_image_url );
		}
	}

	/**
	 * Preload poster image for the LCP <video> element.
	 *
	 * @since n.e.x.t
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context, with the cursor currently at an embed block.
	 */
	private function preload_poster_image( OD_Tag_Visitor_Context $context ): void {
		$processor = $context->processor;

		// Skip empty poster attributes and data: URLs.
		$poster = trim( (string) $processor->get_attribute( 'poster' ) );
		if ( '' === $poster || $this->is_data_url( $poster ) ) {
			return;
		}

		$xpath = $processor->get_xpath();

		// If this element is the LCP (for a breakpoint group), add a preload link for it.
		foreach ( $context->url_metric_group_collection->get_groups_by_lcp_element( $xpath ) as $group ) {
			$link_attributes = array(
				'rel'           => 'preload',
				'fetchpriority' => 'high',
				'as'            => 'image',
				'href'          => $poster,
				'media'         => 'screen',
			);

			$crossorigin = $this->get_attribute_value( $processor, 'crossorigin' );
			if ( null !== $crossorigin ) {
				$link_attributes['crossorigin'] = 'use-credentials' === $crossorigin ? 'use-credentials' : 'anonymous';
			}

			$context->link_collection->add_link(
				$link_attributes,
				$group->get_minimum_viewport_width(),
				$group->get_maximum_viewport_width()
			);
		}
	}
}
