<?php
/**
 * Image Prioritizer: IP_Img_Tag_Visitor class
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
 * Tag visitor that optimizes IMG tags.
 *
 * @phpstan-import-type LinkAttributes from OD_Link_Collection
 *
 * @since 0.1.0
 * @access private
 */
final class Image_Prioritizer_Img_Tag_Visitor extends Image_Prioritizer_Tag_Visitor {

	/**
	 * Visits a tag.
	 *
	 * @since 0.1.0
	 * @since 0.3.0 Separate the processing of IMG and PICTURE elements.
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context.
	 * @return bool Whether the tag should be tracked in URL Metrics.
	 */
	public function __invoke( OD_Tag_Visitor_Context $context ): bool {
		$processor = $context->processor;
		$tag       = $processor->get_tag();

		if ( 'PICTURE' === $tag ) {
			return $this->process_picture( $processor, $context );
		} elseif ( 'IMG' === $tag ) {
			return $this->process_img( $processor, $context );
		}

		return false;
	}

	/**
	 * Process an IMG element.
	 *
	 * @since 0.3.0
	 *
	 * @param OD_HTML_Tag_Processor  $processor HTML tag processor.
	 * @param OD_Tag_Visitor_Context $context   Tag visitor context.
	 * @return bool Whether the tag should be tracked in URL Metrics.
	 */
	private function process_img( OD_HTML_Tag_Processor $processor, OD_Tag_Visitor_Context $context ): bool {
		$src = $this->get_valid_src( $processor );
		if ( null === $src ) {
			return false;
		}

		$xpath = $processor->get_xpath();

		$current_fetchpriority = $this->get_attribute_value( $processor, 'fetchpriority' );
		$is_lazy_loaded        = 'lazy' === $this->get_attribute_value( $processor, 'loading' );
		$updated_fetchpriority = null;

		/*
		 * When the same LCP element is common/shared among all viewport groups, make sure that the element has
		 * fetchpriority=high, even though it won't really be needed because a preload link with fetchpriority=high
		 * will also be added. Additionally, ensure that this common LCP element is never lazy-loaded.
		 */
		$common_lcp_element = $context->url_metric_group_collection->get_common_lcp_element();
		if ( $common_lcp_element instanceof OD_Element && $xpath === $common_lcp_element->get_xpath() ) {
			$updated_fetchpriority = 'high';
		} elseif (
			'high' === $current_fetchpriority
			&&
			$context->url_metric_group_collection->is_any_group_populated()
		) {
			/*
			 * At this point, the element is not the shared LCP across all viewport groups. Nevertheless, server-side
			 * heuristics have added fetchpriority=high to the element, but this is not warranted either due to a lack
			 * of data or because the LCP element is not common across all viewport groups. Since we have collected at
			 * least some URL Metrics (per is_any_group_populated), further below a fetchpriority=high preload link will
			 * be added for the viewport(s) for which this is actually the LCP element. Some viewport groups may never
			 * get populated due to a lack of traffic (e.g. from tablets or phablets), so it is important to remove
			 * fetchpriority=high in such case to prevent server-side heuristics from prioritizing loading the image
			 * which isn't actually the LCP element for actual visitors.
			 */
			$updated_fetchpriority = false; // That is, remove it.
		}

		/*
		 * Do not do any lazy-loading if the mobile and desktop viewport groups lack URL Metrics. This is important
		 * because if there is an IMG in the initial viewport on desktop but not mobile, if then there are only URL
		 * metrics collected for mobile then the IMG will get lazy-loaded which is good for mobile but for desktop
		 * it will hurt performance. So this is why it is important to have URL Metrics collected for both desktop and
		 * mobile to verify whether maximum intersectionRatio is accounting for both screen sizes.
		 */
		$element_max_intersection_ratio = $context->url_metric_group_collection->get_element_max_intersection_ratio( $xpath );

		// If the element was not found, we don't know if it was visible for not, so don't do anything.
		if ( is_null( $element_max_intersection_ratio ) ) {
			$processor->set_meta_attribute( 'unknown-tag', true ); // Mostly useful for debugging why an IMG isn't optimized.
		} elseif (
			$context->url_metric_group_collection->get_first_group()->count() > 0
			&&
			$context->url_metric_group_collection->get_last_group()->count() > 0
		) {
			// TODO: Take into account whether the element has the computed style of visibility:hidden, in such case it should also get fetchpriority=low.
			// Otherwise, make sure visible elements omit the loading attribute, and hidden elements include loading=lazy.
			$is_visible = $element_max_intersection_ratio > 0.0;
			if ( true === $context->url_metric_group_collection->is_element_positioned_in_any_initial_viewport( $xpath ) ) {
				if ( ! $is_visible ) {
					// If an element is positioned in the initial viewport and yet it is it not visible, it may be
					// located in a subsequent carousel slide or inside a hidden navigation menu which could be
					// displayed at any time. Therefore, it should get fetchpriority=low so that any images which are
					// visible can be loaded with a higher priority.
					$updated_fetchpriority = 'low';

					// Also prevent the image from being lazy-loaded (or eager-loaded) since it may be revealed at any
					// time without the browser having any signal (e.g. user scrolling toward it) to start downloading.
					$processor->remove_attribute( 'loading' );
				} elseif ( $is_lazy_loaded ) {
					// Otherwise, if the image is positioned inside any initial viewport then it should never get lazy-loaded.
					$processor->remove_attribute( 'loading' );
				}
			} elseif ( ! $is_lazy_loaded && ! $is_visible ) {
				// Otherwise, the element is not positioned in any initial viewport, so it should always get lazy-loaded.
				// The `! $is_visible` condition should always evaluate to true since the intersectionRatio of an
				// element positioned below the initial viewport should by definition never be visible.
				$processor->set_attribute( 'loading', 'lazy' );
			}
		}
		// TODO: If an image is visible in one breakpoint but not another, add loading=lazy AND add a regular-priority preload link with media queries (unless LCP in which case it should already have a fetchpriority=high link) so that the image won't be eagerly-loaded for viewports on which it is not shown.

		// Set the fetchpriority attribute if needed.
		if ( is_string( $updated_fetchpriority ) ) {
			if ( $updated_fetchpriority !== $current_fetchpriority ) {
				$processor->set_attribute( 'fetchpriority', $updated_fetchpriority );
			} else {
				$processor->set_meta_attribute( 'fetchpriority-already-added', true );
			}
		} elseif ( false === $updated_fetchpriority ) {
			$processor->remove_attribute( 'fetchpriority' );
		}

		// Ensure that sizes is set properly when it is a responsive image (it has a srcset attribute).
		if ( is_string( $processor->get_attribute( 'srcset' ) ) ) {
			$sizes = $processor->get_attribute( 'sizes' );
			if ( ! is_string( $sizes ) ) {
				$sizes = '';
			}

			$is_lazy  = 'lazy' === $this->get_attribute_value( $processor, 'loading' );
			$has_auto = $this->sizes_attribute_includes_valid_auto( $sizes );

			if ( $is_lazy && ! $has_auto ) {
				$new_sizes = 'auto';
				if ( '' !== trim( $sizes, " \t\f\r\n" ) ) {
					$new_sizes .= ', ';
				}
				$sizes = $new_sizes . $sizes;
			} elseif ( ! $is_lazy && $has_auto ) {
				// Remove auto from the beginning of the list.
				$sizes = (string) preg_replace( '/^[ \t\f\r\n]*auto[ \t\f\r\n]*(,[ \t\f\r\n]*)?/i', '', $sizes );
			}

			// Compute more accurate sizes when it isn't lazy-loaded and sizes=auto isn't taking care of it.
			if ( ! $is_lazy ) {
				$computed_sizes = $this->compute_sizes( $context );
				if ( count( $computed_sizes ) > 0 ) {
					$new_sizes = join( ', ', $computed_sizes );

					// Preserve the original sizes as a fallback when URL Metrics are missing from one or more viewport group.
					// Note that when all groups are populated, the media features will span all possible viewport widths from
					// zero to infinity, so there is no need to include the original sizes since they will never match.
					if ( '' !== $sizes && ! $context->url_metric_group_collection->is_every_group_populated() ) {
						$new_sizes .= ", $sizes";
					}
					$sizes = $new_sizes;
				}
			}

			$processor->set_attribute( 'sizes', $sizes );
		}

		$parent_tag = $this->get_parent_tag_name( $context );
		if ( 'PICTURE' !== $parent_tag ) {
			$this->add_image_preload_link_for_lcp_element_groups(
				$context,
				$xpath,
				array(
					'href'           => $processor->get_attribute( 'src' ),
					'imagesrcset'    => $processor->get_attribute( 'srcset' ),
					'imagesizes'     => $processor->get_attribute( 'sizes' ),
					'crossorigin'    => $this->get_attribute_value( $processor, 'crossorigin' ),
					'referrerpolicy' => $this->get_attribute_value( $processor, 'referrerpolicy' ),
				)
			);
		}

		return true;
	}

	/**
	 * Process a PICTURE element.
	 *
	 * @since 0.3.0
	 *
	 * @param OD_HTML_Tag_Processor  $processor HTML tag processor.
	 * @param OD_Tag_Visitor_Context $context   Tag visitor context.
	 * @return bool Whether the tag should be tracked in URL Metrics.
	 */
	private function process_picture( OD_HTML_Tag_Processor $processor, OD_Tag_Visitor_Context $context ): bool {
		/**
		 * First SOURCE tag's attributes.
		 *
		 * @var array{ srcset: non-empty-string, sizes: string|null, type: non-empty-string }|null $first_source
		 */
		$first_source = null;
		$img_xpath    = null;

		$referrerpolicy = null;
		$crossorigin    = null;

		// Loop through child tags until we reach the closing PICTURE tag.
		// As of 1.0.0-beta3, next_tag() allows $query and is beginning to migrate to skip tag closers by default.
		// In versions prior to this, the method always visited closers and passing a $query actually threw an exception.
		$tag_query = version_compare( OPTIMIZATION_DETECTIVE_VERSION, '1.0.0-beta3', '>=' )
			? array( 'tag_closers' => 'visit' ) : null;
		while ( $processor->next_tag( $tag_query ) ) {
			$tag = $processor->get_tag();

			// If we reached the closing PICTURE tag, break.
			if ( 'PICTURE' === $tag && $processor->is_tag_closer() ) {
				break;
			}

			// Process the SOURCE elements.
			if ( 'SOURCE' === $tag && ! $processor->is_tag_closer() ) {
				// Abort processing if the PICTURE involves art direction since then adding a preload link is infeasible.
				if ( null !== $processor->get_attribute( 'media' ) ) {
					return false;
				}

				// Abort processing if a SOURCE lacks the required srcset attribute.
				$srcset = $this->get_valid_src( $processor, 'srcset' );
				if ( null === $srcset ) {
					return false;
				}

				// Abort processing if there is no valid image type.
				$type = $this->get_attribute_value( $processor, 'type' );
				if ( ! is_string( $type ) || ! str_starts_with( $type, 'image/' ) ) {
					return false;
				}

				// Collect the first valid SOURCE as the preload link.
				if ( null === $first_source ) {
					$sizes        = $processor->get_attribute( 'sizes' );
					$first_source = array(
						'srcset' => $srcset,
						'sizes'  => is_string( $sizes ) ? $sizes : null,
						'type'   => $type,
					);
				}
			}

			// Process the IMG element within the PICTURE.
			if ( 'IMG' === $tag && ! $processor->is_tag_closer() ) {
				$src = $this->get_valid_src( $processor );
				if ( null === $src ) {
					return false;
				}

				// These attributes are only defined on the IMG itself.
				$referrerpolicy = $this->get_attribute_value( $processor, 'referrerpolicy' );
				$crossorigin    = $this->get_attribute_value( $processor, 'crossorigin' );

				// Capture the XPath for the IMG since the browser captures it as the LCP element, so we need this to
				// look up whether it is the LCP element in the URL Metric groups.
				$img_xpath = $processor->get_xpath();
			}
		}

		// Abort if we never encountered a SOURCE or IMG tag.
		if ( null === $img_xpath || null === $first_source ) {
			return false;
		}

		$this->add_image_preload_link_for_lcp_element_groups(
			$context,
			$img_xpath,
			array(
				'imagesrcset'    => $first_source['srcset'],
				'imagesizes'     => $first_source['sizes'],
				'type'           => $first_source['type'],
				'crossorigin'    => $crossorigin,
				'referrerpolicy' => $referrerpolicy,
			)
		);

		return false;
	}

	/**
	 * Gets valid src attribute value for preloading.
	 *
	 * Returns null if the src attribute is not a string (i.e. src was used as a boolean attribute was used), if it
	 * it has an empty string value after trimming, or if it is a data: URL.
	 *
	 * @since 0.3.0
	 *
	 * @param OD_HTML_Tag_Processor $processor      Processor.
	 * @param 'src'|'srcset'        $attribute_name Attribute name.
	 * @return non-empty-string|null URL which is not a data: URL.
	 */
	private function get_valid_src( OD_HTML_Tag_Processor $processor, string $attribute_name = 'src' ): ?string {
		$src = $processor->get_attribute( $attribute_name );
		if ( ! is_string( $src ) ) {
			return null;
		}
		$src = trim( $src );
		if ( '' === $src || $this->is_data_url( $src ) ) {
			return null;
		}
		return $src;
	}

	/**
	 * Adds a LINK with the supplied attributes for each viewport group when the provided XPath is the LCP element.
	 *
	 * @since 0.3.0
	 *
	 * @param OD_Tag_Visitor_Context          $context    Tag visitor context.
	 * @param string                          $xpath      XPath of the element.
	 * @param array<string, string|true|null> $attributes Attributes to add to the link.
	 */
	private function add_image_preload_link_for_lcp_element_groups( OD_Tag_Visitor_Context $context, string $xpath, array $attributes ): void {
		$attributes = array_filter(
			$attributes,
			static function ( $attribute_value ) {
				return is_string( $attribute_value ) && '' !== $attribute_value;
			}
		);

		/**
		 * Link attributes.
		 *
		 * This type is needed because PHPStan isn't apparently aware of the new keys added after the array_merge().
		 * Note that there is no type checking being done on the attributes above other than ensuring they are
		 * non-empty-strings.
		 *
		 * @var LinkAttributes $attributes
		 */
		$attributes = array_merge(
			array(
				'rel'           => 'preload',
				'fetchpriority' => 'high',
				'as'            => 'image',
			),
			$attributes,
			array(
				'media' => 'screen',
			)
		);

		foreach ( $context->url_metric_group_collection->get_groups_by_lcp_element( $xpath ) as $group ) {
			$context->link_collection->add_link(
				$attributes,
				$group->get_minimum_viewport_width(),
				$group->get_maximum_viewport_width()
			);
		}
	}

	/**
	 * Gets the parent tag name.
	 *
	 * @since 0.3.0
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context.
	 * @return string|null The parent tag name or null if not found.
	 */
	private function get_parent_tag_name( OD_Tag_Visitor_Context $context ): ?string {
		$breadcrumbs = $context->processor->get_breadcrumbs();
		$length      = count( $breadcrumbs );
		if ( $length < 2 ) {
			return null;
		}
		return $breadcrumbs[ $length - 2 ];
	}

	/**
	 * Checks whether the given 'sizes' attribute includes the 'auto' keyword as the first item in the list.
	 *
	 * Per the HTML spec, if present it must be the first entry.
	 *
	 * @since 0.1.4
	 *
	 * @param string $sizes_attr The 'sizes' attribute value.
	 * @return bool True if the 'auto' keyword is present, false otherwise.
	 */
	private function sizes_attribute_includes_valid_auto( string $sizes_attr ): bool {
		if ( function_exists( 'wp_sizes_attribute_includes_valid_auto' ) ) {
			return wp_sizes_attribute_includes_valid_auto( $sizes_attr );
		} elseif ( function_exists( 'auto_sizes_attribute_includes_valid_auto' ) ) {
			return auto_sizes_attribute_includes_valid_auto( $sizes_attr );
		} else {
			return 'auto' === $sizes_attr || str_starts_with( $sizes_attr, 'auto,' );
		}
	}

	/**
	 * Computes responsive sizes for the current element based on its boundingClientRect width captured in URL Metrics.
	 *
	 * @since 1.0.0
	 *
	 * @param OD_Tag_Visitor_Context $context Context.
	 * @return non-empty-string[] Computed sizes.
	 */
	private function compute_sizes( OD_Tag_Visitor_Context $context ): array {
		$sizes = array();

		$xpath = $context->processor->get_xpath();
		foreach ( $context->url_metric_group_collection as $group ) {
			// Obtain the maximum width that the image appears among all URL Metrics collected for this viewport group.
			$element_max_width = 0;
			foreach ( $group->get_xpath_elements_map()[ $xpath ] ?? array() as $element ) {
				$element_max_width = max( $element_max_width, $element->get_bounding_client_rect()['width'] );
			}

			// Use the maximum width as the size for image in this breakpoint.
			if ( $element_max_width > 0 ) {
				$size          = sprintf( '%dpx', $element_max_width );
				$media_feature = od_generate_media_query( $group->get_minimum_viewport_width(), $group->get_maximum_viewport_width() );
				if ( null !== $media_feature ) {
					// Note: The null case only happens when a site has filtered od_breakpoint_max_widths to be an empty array, meaning there is only one viewport group.
					$size = "$media_feature $size";
				}
				$sizes[] = $size;
			}
		}

		return $sizes;
	}
}
