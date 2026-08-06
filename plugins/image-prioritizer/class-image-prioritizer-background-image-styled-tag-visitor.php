<?php
/**
 * Image Prioritizer: IP_Background_Image_Styled_Tag_Visitor class
 *
 * @package image-prioritizer
 * @since 0.1.0
 */

declare( strict_types = 1 );

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
	 * Pattern for matching the URL of a background image in a `style` attribute.
	 *
	 * @since n.e.x.t
	 * @var non-empty-string
	 */
	const BACKGROUND_IMAGE_PATTERN = '/background(?:-image)?\s*:[^;]*?url\(\s*[\'"]?\s*(?<background_image>.+?)\s*[\'"]?\s*\)/';

	/**
	 * Name of the attribute which contains the attachment ID for an element's background image.
	 *
	 * This is added by {@see image_prioritizer_add_background_image_attachment_id()}.
	 *
	 * @since n.e.x.t
	 * @var non-empty-string
	 */
	const ATTACHMENT_ID_ATTR_NAME = 'data-image-prioritizer-bg-attachment-id';

	/**
	 * Whether the lazy-loading script and stylesheet have been added.
	 *
	 * @since 0.3.0
	 * @var bool
	 */
	private bool $added_lazy_assets = false;

	/**
	 * Tuples of URL Metric group and the common LCP element external background image.
	 *
	 * Lazily populated in {@see self::maybe_preload_external_lcp_background_image()}.
	 *
	 * @since 0.3.0
	 * @var array<array{OD_URL_Metric_Group, LcpElementExternalBackgroundImage}>|null
	 */
	private ?array $group_common_lcp_element_external_background_images = null;

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
			1 === preg_match( self::BACKGROUND_IMAGE_PATTERN, $style, $matches )
			&&
			! $this->is_data_url( $matches['background_image'] )
		) {
			$background_image_url = $matches['background_image'];
		}

		if ( is_null( $background_image_url ) ) {
			$this->maybe_preload_external_lcp_background_image( $context );
			return false;
		}

		// Reduce the background image size if URL Metrics are available. Note this must happen before the preload link
		// is added below so that the preloaded image is the same one that ends up being used.
		$background_image_url = $this->reduce_background_image_size( $background_image_url, $context );

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
		if ( null === $this->group_common_lcp_element_external_background_images ) {
			$this->group_common_lcp_element_external_background_images = array();
			foreach ( $context->url_metric_group_collection as $group ) {
				$common = $this->get_common_lcp_element_external_background_image( $group );
				if ( is_array( $common ) ) {
					$this->group_common_lcp_element_external_background_images[] = array( $group, $common );
				}
			}
		}

		$tuples = $this->group_common_lcp_element_external_background_images;

		// There are no common LCP background images, so abort.
		if ( count( $tuples ) === 0 ) {
			return;
		}

		$processor = $context->processor;
		$tag_name  = strtoupper( (string) $processor->get_tag() );
		foreach ( array_keys( $tuples ) as $i ) {
			list( $group, $common ) = $tuples[ $i ];
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

	/**
	 * Reduces background image size by choosing one that fits the element dimensions more closely.
	 *
	 * This is similar to how VIDEO poster images are optimized in the Video Tag Visitor. Since a background image has no
	 * `srcset` for the browser to select an appropriately-sized image from, the full size image is often used even when
	 * the element it is displayed in is much smaller.
	 *
	 * @since n.e.x.t
	 *
	 * @param non-empty-string       $background_image_url Background image URL.
	 * @param OD_Tag_Visitor_Context $context              Tag visitor context, with the cursor currently at an element with a background image.
	 * @return non-empty-string The background image URL which is now used by the element.
	 */
	private function reduce_background_image_size( string $background_image_url, OD_Tag_Visitor_Context $context ): string {
		$processor = $context->processor;

		// The attachment ID is only known for background images added by a block. See image_prioritizer_add_background_image_attachment_id().
		$attachment_id = $processor->get_attribute( self::ATTACHMENT_ID_ATTR_NAME );
		if ( ! is_string( $attachment_id ) || ! is_numeric( $attachment_id ) || (int) $attachment_id <= 0 ) {
			return $background_image_url;
		}

		$max_element_width = $this->get_max_element_width( $context );

		// If the element wasn't present in any URL Metrics gathered for desktop, then abort downsizing the background image.
		if ( null === $max_element_width ) {
			return $background_image_url;
		}

		$smaller_image_url = $this->get_smaller_image_url( (int) $attachment_id, $background_image_url, (int) $max_element_width );
		if ( null === $smaller_image_url ) {
			return $background_image_url;
		}

		// Replace the background image URL in the style attribute.
		$style = $processor->get_attribute( 'style' );
		if ( ! is_string( $style ) ) {
			return $background_image_url;
		}
		$processor->set_attribute( 'style', str_replace( $background_image_url, $smaller_image_url, $style ) );

		return $smaller_image_url;
	}

	/**
	 * Gets the URL for a smaller version of an attachment's image, if one is available.
	 *
	 * This ensures a larger image is never served in place of the image currently being used, which could otherwise
	 * happen when the current image is already smaller than the element it is displayed in.
	 *
	 * @since n.e.x.t
	 *
	 * @param int              $attachment_id Attachment ID.
	 * @param non-empty-string $current_url   URL of the image currently being used.
	 * @param int              $max_width     Maximum width at which the image is displayed.
	 * @return non-empty-string|null URL for a smaller image, or null if no smaller image is available.
	 */
	private function get_smaller_image_url( int $attachment_id, string $current_url, int $max_width ): ?string {
		if ( $max_width <= 0 ) {
			return null;
		}

		$smaller_image = wp_get_attachment_image_src( $attachment_id, array( $max_width, 0 ) );
		if ( false === $smaller_image || '' === $smaller_image[0] || $smaller_image[0] === $current_url ) {
			return null;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) || ! isset( $metadata['width'] ) ) {
			return null;
		}

		// Determine the width of the image currently being used, falling back to the full size width when the URL is not recognized.
		$current_width = (int) $metadata['width'];
		$basename      = wp_basename( (string) wp_parse_url( $current_url, PHP_URL_PATH ) );
		foreach ( $metadata['sizes'] ?? array() as $size ) {
			if ( isset( $size['file'], $size['width'] ) && $size['file'] === $basename ) {
				$current_width = (int) $size['width'];
				break;
			}
		}

		if ( $smaller_image[1] >= $current_width ) {
			return null;
		}

		return $smaller_image[0];
	}
}
