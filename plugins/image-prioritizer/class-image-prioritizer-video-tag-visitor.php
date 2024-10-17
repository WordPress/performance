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
	 * Whether the lazy-loading script was added to the body.
	 *
	 * @var bool
	 */
	protected $added_lazy_script = false;

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

		$poster = $this->get_poster( $context );

		if ( null !== $poster ) {
			$this->reduce_poster_image_size( $poster, $context );
			$this->preload_poster_image( $poster, $context );
		}

		$this->lazy_load_videos( $poster, $context );

		return true;
	}

	/**
	 * Gets the poster from the current VIDEO element.
	 *
	 * Skips empty poster attributes and data: URLs.
	 *
	 * @since n.e.x.t
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context.
	 * @return non-empty-string|null Poster or null if not defined or is a data: URL.
	 */
	private function get_poster( OD_Tag_Visitor_Context $context ): ?string {
		$poster = trim( (string) $context->processor->get_attribute( 'poster' ) );
		if ( '' === $poster || $this->is_data_url( $poster ) ) {
			return null;
		}
		return $poster;
	}

	/**
	 * Reduces poster image size by choosing one that fits the maximum video size more closely.
	 *
	 * @since n.e.x.t
	 *
	 * @param non-empty-string       $poster  Poster image URL.
	 * @param OD_Tag_Visitor_Context $context Tag visitor context, with the cursor currently at a VIDEO tag.
	 */
	private function reduce_poster_image_size( string $poster, OD_Tag_Visitor_Context $context ): void {
		$processor = $context->processor;

		$xpath = $processor->get_xpath();

		/*
		 * Obtain maximum width of the element exclusively from the URL metrics group with the widest viewport width,
		 * which would be desktop. This prevents the situation where if URL metrics have only so far been gathered for
		 * mobile viewports that an excessively-small poster would end up getting served to the first desktop visitor.
		 */
		$max_element_width = 0;
		$widest_group      = array_reduce(
			iterator_to_array( $context->url_metric_group_collection ),
			static function ( $carry, OD_URL_Metric_Group $group ) {
				return ( null === $carry || $group->get_minimum_viewport_width() > $carry->get_minimum_viewport_width() ) ? $group : $carry;
			}
		);
		foreach ( $widest_group as $url_metric ) {
			foreach ( $url_metric->get_elements() as $element ) {
				if ( $element['xpath'] === $xpath ) {
					$max_element_width = max( $max_element_width, $element['boundingClientRect']['width'] );
					break; // Move on to the next URL Metric.
				}
			}
		}

		// If the element wasn't present in any URL Metrics gathered for desktop, then abort downsizing the poster.
		if ( 0 === $max_element_width ) {
			return;
		}

		$poster_id = attachment_url_to_postid( $poster );

		if ( $poster_id > 0 ) {
			$smaller_image_url = wp_get_attachment_image_url( $poster_id, array( (int) $max_element_width, 0 ) );
			if ( is_string( $smaller_image_url ) ) {
				$processor->set_attribute( 'poster', $smaller_image_url );
			}
		}
	}

	/**
	 * Preloads poster image for the LCP <video> element.
	 *
	 * @since n.e.x.t
	 *
	 * @param non-empty-string       $poster  Poster image URL.
	 * @param OD_Tag_Visitor_Context $context Tag visitor context, with the cursor currently at a VIDEO tag.
	 */
	private function preload_poster_image( string $poster, OD_Tag_Visitor_Context $context ): void {
		$processor = $context->processor;

		$xpath = $processor->get_xpath();

		// If this element is the LCP (for a breakpoint group), add a preload link for the poster image.
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

	/**
	 * Adjusts `autoplay` and `preload` values for videos outside initial viewport.
	 *
	 * @since n.e.x.t
	 *
	 * @param non-empty-string|null  $poster  Poster image URL.
	 * @param OD_Tag_Visitor_Context $context Tag visitor context, with the cursor currently at an embed block.
	 */
	private function lazy_load_videos( ?string $poster, OD_Tag_Visitor_Context $context ): void {
		$processor = $context->processor;

		$xpath = $processor->get_xpath();

		$intersection_ratio = $context->url_metric_group_collection->get_element_max_intersection_ratio( $xpath );

		// Set preload="auto" if the video is the LCP element among all viewports.
		$common_lcp_element = $context->url_metric_group_collection->get_common_lcp_element();
		if ( null !== $common_lcp_element && $xpath === $common_lcp_element['xpath'] ) {
			$processor->set_attribute( 'preload', 'auto' );
			return;
		}

		// TODO: What if URL metrics aren't available for all viewports yet?
		if ( $intersection_ratio > 0 ) {
			return;
		}

		$preload = $processor->get_attribute( 'preload' );
		if ( 'none' !== $preload ) {
			$processor->set_attribute( 'data-original-preload', null !== $preload ? $preload : 'default' );
			$processor->set_attribute( 'preload', 'none' );
			$processor->add_class( 'wp-lazy-video' );
		}

		if ( null !== $processor->get_attribute( 'autoplay' ) ) {
			$processor->set_attribute( 'data-original-autoplay', true );
			$processor->remove_attribute( 'autoplay' );
			$processor->add_class( 'wp-lazy-video' );
		}

		if ( null !== $poster ) {
			$processor->set_attribute( 'data-original-poster', $poster );
			$processor->remove_attribute( 'poster' );
			$processor->add_class( 'wp-lazy-video' );
		}

		if ( ! $this->added_lazy_script ) {
			$processor->append_body_html( wp_get_inline_script_tag( image_prioritizer_get_lazy_load_script(), array( 'type' => 'module' ) ) );
			$this->added_lazy_script = true;
		}
	}
}
