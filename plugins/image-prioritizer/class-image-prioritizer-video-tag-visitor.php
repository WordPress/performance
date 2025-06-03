<?php
/**
 * Tag visitor that optimizes VIDEO tags:
 * - Adds preload links for poster images if in a breakpoint group's LCP.
 *
 * @package image-prioritizer
 *
 * @since 0.2.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Image Prioritizer: Image_Prioritizer_Video_Tag_Visitor class
 *
 * @since 0.2.0
 * @access private
 */
final class Image_Prioritizer_Video_Tag_Visitor extends Image_Prioritizer_Tag_Visitor {

	/**
	 * Class name used to indicate a video which is lazy-loaded.
	 *
	 * @since 0.2.0
	 * @var string
	 */
	const LAZY_VIDEO_CLASS_NAME = 'od-lazy-video';

	/**
	 * Whether the lazy-loading script was added to the body.
	 *
	 * @since 0.2.0
	 * @var bool
	 */
	protected $added_lazy_script = false;

	/**
	 * Visits a tag.
	 *
	 * @since 0.2.0
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context.
	 * @return bool Whether the tag should be tracked in URL Metrics.
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
	 * @since 0.2.0
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
	 * @since 0.2.0
	 *
	 * @param non-empty-string       $poster  Poster image URL.
	 * @param OD_Tag_Visitor_Context $context Tag visitor context, with the cursor currently at a VIDEO tag.
	 */
	private function reduce_poster_image_size( string $poster, OD_Tag_Visitor_Context $context ): void {
		$processor = $context->processor;

		$xpath = $processor->get_xpath();

		/*
		 * Obtain maximum width of the element exclusively from the URL Metrics group with the widest viewport width,
		 * which would be desktop. This prevents the situation where if URL Metrics have only so far been gathered for
		 * mobile viewports that an excessively-small poster would end up getting served to the first desktop visitor.
		 */
		$max_element_width = 0;
		foreach ( $context->url_metric_group_collection->get_last_group() as $url_metric ) {
			foreach ( $url_metric->get_elements() as $element ) {
				if ( $element->get_xpath() === $xpath ) {
					$max_element_width = max( $max_element_width, $element->get_bounding_client_rect()['width'] );
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
	 * @since 0.2.0
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
	 * Optimizes the VIDEO tag based on whether it is the LCP element or else whether it is displayed in any initial viewport.
	 *
	 * @since 0.2.0
	 *
	 * @param non-empty-string|null  $poster  Poster image URL.
	 * @param OD_Tag_Visitor_Context $context Tag visitor context, with the cursor currently at an embed block.
	 */
	private function lazy_load_videos( ?string $poster, OD_Tag_Visitor_Context $context ): void {
		$processor = $context->processor;

		/*
		 * Do not do any lazy-loading if the mobile and desktop viewport groups lack URL Metrics. This is important
		 * because if there is a VIDEO in the initial viewport on desktop but not mobile, if then there are only URL
		 * metrics collected for mobile then the VIDEO will get lazy-loaded which is good for mobile but for desktop
		 * it will hurt performance. So this is why it is important to have URL Metrics collected for both desktop and
		 * mobile to verify whether maximum intersectionRatio is accounting for both screen sizes.
		 */
		if (
			$context->url_metric_group_collection->get_first_group()->count() === 0
			||
			$context->url_metric_group_collection->get_last_group()->count() === 0
		) {
			return;
		}

		$xpath = $processor->get_xpath();

		$initial_preload        = $this->get_attribute_value( $processor, 'preload' );
		$max_intersection_ratio = $context->url_metric_group_collection->get_element_max_intersection_ratio( $xpath );

		/*
		 * Optimize the video preload value based on how visible the video is in the viewport. If it is the common LCP
		 * element, then make sure that preload is set to auto so that the browser is encouraged to download more of the
		 * video. Nevertheless, it is likely that get_common_lcp_element() may return null since URL Metrics for
		 * phablets and tablets may not get frequently gathered.
		 *
		 * So, for figure consideration this should perhaps check if the element in all gathered URL Metrics for all
		 * viewport groups has an intersectionRatio greater than zero. If so, then it can also set it to preload=auto.
		 * Otherwise, if the maximum intersectionRatio is greater than zero and yet there are also instances of the
		 * element in one or more URL Metrics for which the intersectionRatio is zero, then in this case it may make
		 * sense to set preload to metadata. This would avoid potentially aggressive downloading of a video when it is
		 * not visible on mobile and yet it is visible on desktop. For prioritizing the loading of image which is the
		 * LCP element for one viewport but not another the solution is to use a preload link with a media query.
		 * However, videos are not supported in preload links, so we have to resort to trying pick the best preload
		 * value  attribute to be shared across all viewports.
		 * TODO: The above paragraph.
		 */
		$common_lcp_element = $context->url_metric_group_collection->get_common_lcp_element();
		if ( $common_lcp_element instanceof OD_Element && $xpath === $common_lcp_element->get_xpath() ) {
			if ( 'auto' !== $initial_preload ) {
				$processor->set_attribute( 'preload', 'auto' );
			}
			return;
		}

		// If the element is visible in any viewport, do not lazy-load it.
		if ( $max_intersection_ratio > 0 ) {
			return;
		}

		if ( 'none' !== $initial_preload ) {
			$processor->set_attribute( 'data-original-preload', null !== $initial_preload ? $initial_preload : 'default' );
			$processor->set_attribute( 'preload', 'none' );
			$processor->add_class( self::LAZY_VIDEO_CLASS_NAME );
		}

		if ( null !== $processor->get_attribute( 'autoplay' ) ) {
			$processor->set_attribute( 'data-original-autoplay', true );
			$processor->remove_attribute( 'autoplay' );
			$processor->add_class( self::LAZY_VIDEO_CLASS_NAME );
		}

		if ( null !== $poster ) {
			$processor->set_attribute( 'data-original-poster', $poster );
			$processor->remove_attribute( 'poster' );
			$processor->add_class( self::LAZY_VIDEO_CLASS_NAME );
		}

		if ( ! $this->added_lazy_script ) {
			$processor->append_body_html( wp_get_inline_script_tag( image_prioritizer_get_video_lazy_load_script(), array( 'type' => 'module' ) ) );
			$this->added_lazy_script = true;
		}
	}
}
