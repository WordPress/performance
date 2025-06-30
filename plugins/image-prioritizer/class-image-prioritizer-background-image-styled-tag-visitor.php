<?php
/**
 * Image Prioritizer: IP_Background_Image_Styled_Tag_Visitor class
 *
 * @package image-prioritizer
 * @since 0.1.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Tag visitor that optimizes elements with background-image styles.
 *
 * @phpstan-type LcpElementExternalBackgroundImage array{
 *     url: non-empty-string,
 *     tag: non-empty-string,
 *     id: string|null,
 *     class: string|null,
 * }
 *
 * @since 0.1.0
 * @access private
 */
final class Image_Prioritizer_Background_Image_Styled_Tag_Visitor extends Image_Prioritizer_Tag_Visitor {

	/**
	 * Class name used to indicate a background image which is lazy-loaded.
	 *
	 * @since 0.3.0
	 * @var string
	 */
	const LAZY_BG_IMAGE_CLASS_NAME = 'od-lazy-bg-image';

	/**
	 * Whether the lazy-loading script and stylesheet have been added.
	 *
	 * @since 0.3.0
	 * @var bool
	 */
	private $added_lazy_assets = false;

	/**
	 * Tuples of URL Metric group and the common LCP element external background image.
	 *
	 * @since 0.3.0
	 * @var array<array{OD_URL_Metric_Group, LcpElementExternalBackgroundImage}>
	 */
	private $group_common_lcp_element_external_background_images;

	/**
	 * Visits a tag.
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context.
	 * @return bool Whether the tag should be tracked in URL Metrics.
	 */
	public function __invoke( OD_Tag_Visitor_Context $context ): bool {
		$processor = $context->processor;

		/*
		 * Note that CSS allows for a `background`/`background-image` to have multiple `url()` CSS functions, resulting
		 * in multiple background images being layered on top of each other. This ability is not employed in core. Here
		 * is a regex to search WPDirectory for instances of this: /background(-image)?:[^;}]+?url\([^;}]+?[^_]url\(/.
		 * It is used in Jetpack with the second background image being a gradient. To support multiple background
		 * images, this logic would need to be modified to make $background_image an array and to have a more robust
		 * parser of the `url()` functions from the property value.
		 */
		$background_image_url = null;
		$style                = $processor->get_attribute( 'style' );
		if (
			is_string( $style )
			&&
			1 === preg_match( '/background(?:-image)?\s*:[^;]*?url\(\s*[\'"]?\s*(?<background_image>.+?)\s*[\'"]?\s*\)/', $style, $matches )
			&&
			! $this->is_data_url( $matches['background_image'] )
		) {
			$background_image_url = $matches['background_image'];
		}

		if ( is_null( $background_image_url ) ) {
			$this->maybe_preload_external_lcp_background_image( $context );
			return false;
		}

		$xpath = $processor->get_xpath();

		// If this element is the LCP (for a breakpoint group), add a preload link for it.
		foreach ( $context->url_metric_group_collection->get_groups_by_lcp_element( $xpath ) as $group ) {
			$this->add_image_preload_link( $context->link_collection, $group, $background_image_url );
		}

		$this->lazy_load_bg_images( $context );

		return true;
	}

	/**
	 * Gets the common LCP element external background image for a URL Metric group.
	 *
	 * @since 0.3.0
	 *
	 * @param OD_URL_Metric_Group $group Group.
	 * @return LcpElementExternalBackgroundImage|null
	 */
	private function get_common_lcp_element_external_background_image( OD_URL_Metric_Group $group ): ?array {

		// If the group is not fully populated, we don't have enough URL Metrics to reliably know whether the background image is consistent across page loads.
		// This is intentionally not using $group->is_complete() because we still will use stale URL Metrics in the calculation.
		if ( $group->count() !== $group->get_sample_size() ) {
			return null;
		}

		$previous_lcp_element_external_background_image = null;
		foreach ( $group as $url_metric ) {
			/**
			 * Stored data.
			 *
			 * @var LcpElementExternalBackgroundImage|null $lcp_element_external_background_image
			 */
			$lcp_element_external_background_image = $url_metric->get( 'lcpElementExternalBackgroundImage' );
			if ( ! is_array( $lcp_element_external_background_image ) ) {
				return null;
			}
			if ( null !== $previous_lcp_element_external_background_image && $previous_lcp_element_external_background_image !== $lcp_element_external_background_image ) {
				return null;
			}
			$previous_lcp_element_external_background_image = $lcp_element_external_background_image;
		}

		return $previous_lcp_element_external_background_image;
	}

	/**
	 * Maybe preloads external background image.
	 *
	 * @since 0.3.0
	 *
	 * @param OD_Tag_Visitor_Context $context Context.
	 */
	private function maybe_preload_external_lcp_background_image( OD_Tag_Visitor_Context $context ): void {
		// Gather the tuples of URL Metric group and the common LCP element external background image.
		// Note the groups of URL Metrics do not change across invocations, we just need to compute this once for all.
		// TODO: Instead of populating this here, it could be done once per invocation during the od_start_template_optimization action since the page's OD_URL_Metric_Group_Collection is available there.
		if ( ! is_array( $this->group_common_lcp_element_external_background_images ) ) {
			$this->group_common_lcp_element_external_background_images = array();
			foreach ( $context->url_metric_group_collection as $group ) {
				$common = $this->get_common_lcp_element_external_background_image( $group );
				if ( is_array( $common ) ) {
					$this->group_common_lcp_element_external_background_images[] = array( $group, $common );
				}
			}
		}

		// There are no common LCP background images, so abort.
		if ( count( $this->group_common_lcp_element_external_background_images ) === 0 ) {
			return;
		}

		$processor = $context->processor;
		$tag_name  = strtoupper( (string) $processor->get_tag() );
		foreach ( array_keys( $this->group_common_lcp_element_external_background_images ) as $i ) {
			list( $group, $common ) = $this->group_common_lcp_element_external_background_images[ $i ];
			if (
				// Note that the browser may send a lower-case tag name in the case of XHTML or embedded SVG/MathML, but
				// the HTML Tag Processor is currently normalizing to all upper-case. The HTML Processor on the other
				// hand may return the expected case.
				strtoupper( $common['tag'] ) === $tag_name
				&&
				$processor->get_attribute( 'id' ) === $common['id'] // May be checking equality with null.
				&&
				$processor->get_attribute( 'class' ) === $common['class'] // May be checking equality with null.
			) {
				$this->add_image_preload_link( $context->link_collection, $group, $common['url'] );

				// Now that the preload link has been added, eliminate the entry to stop looking for it while iterating over the rest of the document.
				unset( $this->group_common_lcp_element_external_background_images[ $i ] );
			}
		}
	}

	/**
	 * Adds an image preload link for the group.
	 *
	 * @since 0.3.0
	 *
	 * @param OD_Link_Collection  $link_collection Link collection.
	 * @param OD_URL_Metric_Group $group           URL Metric group.
	 * @param non-empty-string    $url             Image URL.
	 */
	private function add_image_preload_link( OD_Link_Collection $link_collection, OD_URL_Metric_Group $group, string $url ): void {
		$link_collection->add_link(
			array(
				'rel'           => 'preload',
				'fetchpriority' => 'high',
				'as'            => 'image',
				'href'          => $url,
				'media'         => 'screen',
			),
			$group->get_minimum_viewport_width(),
			$group->get_maximum_viewport_width()
		);
	}

	/**
	 * Optimizes an element with a background image based on whether it is displayed in any initial viewport.
	 *
	 * @since 0.3.0
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context, with the cursor currently at block with a background image.
	 */
	private function lazy_load_bg_images( OD_Tag_Visitor_Context $context ): void {
		$processor = $context->processor;

		// Lazy-loading can only be done once there are URL Metrics collected for both mobile and desktop.
		if (
			$context->url_metric_group_collection->get_first_group()->count() === 0
			||
			$context->url_metric_group_collection->get_last_group()->count() === 0
		) {
			return;
		}

		$xpath = $processor->get_xpath();

		// If the element is in the initial viewport, do not lazy load its background image.
		if ( false !== $context->url_metric_group_collection->is_element_positioned_in_any_initial_viewport( $xpath ) ) {
			return;
		}

		$processor->add_class( self::LAZY_BG_IMAGE_CLASS_NAME );

		if ( ! $this->added_lazy_assets ) {
			$processor->append_head_html( sprintf( "<style>\n%s\n</style>\n", image_prioritizer_get_lazy_load_bg_image_stylesheet() ) );
			$processor->append_body_html( wp_get_inline_script_tag( image_prioritizer_get_lazy_load_bg_image_script(), array( 'type' => 'module' ) ) );
			$this->added_lazy_assets = true;
		}
	}
}
