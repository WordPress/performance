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
	 * Video tag.
	 *
	 * @var string
	 */
	const VIDEO = 'VIDEO';

	/**
	 * Poster attribute.
	 *
	 * @var string
	 */
	const POSTER = 'poster';

	/**
	 * Visits a tag.
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context.
	 *
	 * @return bool Whether the tag should be tracked in URL metrics.
	 */
	public function __invoke( OD_Tag_Visitor_Context $context ): bool {
		$processor = $context->processor;
		if ( self::VIDEO !== $processor->get_tag() ) {
			return false;
		}

		// Skip empty poster attributes and data: URLs.
		$poster = trim( (string) $processor->get_attribute( self::POSTER ) );
		if ( '' === $poster || $this->is_data_url( $poster ) ) {
			return false;
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

		return true;
	}
}
