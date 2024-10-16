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

		$poster                 = trim( (string) $processor->get_attribute( 'poster' ) );
		$has_preloadable_poster = ( '' !== $poster && ! $this->is_data_url( $poster ) );
		$crossorigin            = $this->get_attribute_value( $processor, 'crossorigin' );
		$is_autoplay            = null !== $processor->get_attribute( 'autoplay' );
		$is_muted               = null !== $processor->get_attribute( 'muted' );

		$xpath = $processor->get_xpath();

		$added_preload_link = false;
		if ( $is_autoplay && $is_muted ) {
			$sources = array();

			$video_src = trim( (string) $processor->get_attribute( 'src' ) );
			if ( '' !== $video_src && ! $this->is_data_url( $video_src ) ) {
				$sources[] = array(
					'href' => $video_src,
				);
			}

			$count = 0;

			while ( $processor->next_tag() ) {
				if ( 'SOURCE' === $processor->get_tag() ) { // @phpstan-ignore identical.alwaysFalse
					$src = trim( (string) $processor->get_attribute( 'src' ) );
					if ( '' === $src || $this->is_data_url( $src ) ) {
						continue;
					}
					$source = array(
						'href' => $src,
					);

					$media = trim( (string) $processor->get_attribute( 'media' ) );
					if ( '' !== $media ) {
						$source['media'] = $media;
					}
					$type = trim( (string) $processor->get_attribute( 'type' ) );
					if ( '' !== $type ) {
						$source['type'] = $type;
					}
					$sources[] = $source;
				}
				// @phpstan-ignore identical.alwaysTrue
				if ( 'VIDEO' === $processor->get_tag() ) { // That is, the closing </VIDEO> tag.
					break;
				}
			}

			/*
			 * If there is more than one SOURCE defined, we cannot preload the video because there would be multiple
			 * video types to chose between. If we added a preload link for MP4 and WebM, then the result could be that
			 * the browser preloads both even though only one would be used in the VIDEO.
			 */
			if ( count( $sources ) === 1 ) {
				foreach ( $context->url_metric_group_collection->get_groups_by_lcp_element( $xpath ) as $group ) {
					$link_attributes = array_merge(
						array(
							'rel'           => 'preload',
							'fetchpriority' => 'high',
							'as'            => 'video',
						),
						$sources[0]
					);
					if ( null !== $crossorigin ) {
						$link_attributes['crossorigin'] = 'use-credentials' === $crossorigin ? 'use-credentials' : 'anonymous';
					}
					$context->link_collection->add_link(
						$link_attributes,
						$group->get_minimum_viewport_width(),
						$group->get_maximum_viewport_width()
					);
					$added_preload_link = true;
				}
			}
		}

		// If this element is the LCP (for a breakpoint group), add a preload link for it.
		if ( $has_preloadable_poster && ! $added_preload_link ) {
			foreach ( $context->url_metric_group_collection->get_groups_by_lcp_element( $xpath ) as $group ) {
				$link_attributes = array(
					'rel'           => 'preload',
					'fetchpriority' => 'high',
					'as'            => 'image',
					'href'          => $poster,
					'media'         => 'screen',
				);
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

		return true;
	}
}
