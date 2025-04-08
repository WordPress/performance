<?php
/**
 * Functionality to improve the calculation of image `sizes` attributes.
 *
 * @package auto-sizes
 * @since 1.4.0
 */

/*
 * Map alignment values to a weighting value so they can be compared.
 * Note that 'left' and 'right' alignments are only constrained by max alignment.
 */
const AUTO_SIZES_CONSTRAINTS = array(
	'full'    => 0,
	'wide'    => 1,
	'left'    => 2,
	'right'   => 2,
	'default' => 3,
	'center'  => 3,
);

/**
 * Primes attachment into the cache with a single database query.
 *
 * @since 1.4.0
 *
 * @param string|mixed $content The HTML content.
 * @return string The HTML content.
 */
function auto_sizes_prime_attachment_caches( $content ): string {
	if ( ! is_string( $content ) ) {
		return '';
	}

	$processor = new WP_HTML_Tag_Processor( $content );

	$images = array();
	while ( $processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
		$class = $processor->get_attribute( 'class' );

		if ( ! is_string( $class ) ) {
			continue;
		}

		if ( preg_match( '/(?:^|\s)wp-image-([1-9][0-9]*)(?:\s|$)/', $class, $class_id ) === 1 ) {
			$attachment_id = (int) $class_id[1];
			if ( $attachment_id > 0 ) {
				$images[] = $attachment_id;
			}
		}
	}

	// Reduce the array to unique attachment IDs.
	$attachment_ids = array_unique( $images );

	if ( count( $attachment_ids ) > 1 ) {
		/*
		 * Warm the object cache with post and meta information for all found
		 * images to avoid making individual database calls.
		 */
		_prime_post_caches( $attachment_ids, false, true );
	}

	return $content;
}

/**
 * Filter the sizes attribute for images to improve the default calculation.
 *
 * @since 1.1.0
 *
 * @param string|mixed                                             $content      The block content about to be rendered.
 * @param array{ attrs?: array{ align?: string, width?: string } } $parsed_block The parsed block.
 * @param WP_Block                                                 $block        Block instance.
 * @return string The updated block content.
 */
function auto_sizes_filter_image_tag( $content, array $parsed_block, WP_Block $block ): string {
	if ( ! is_string( $content ) ) {
		return '';
	}

	$processor = new WP_HTML_Tag_Processor( $content );
	$has_image = $processor->next_tag( array( 'tag_name' => 'IMG' ) );

	// Only update the markup if an image is found.
	if ( $has_image ) {

		/**
		 * Callback for calculating image sizes attribute value for an image block.
		 *
		 * This is a workaround to use block context data when calculating the img sizes attribute.
		 *
		 * @param string $sizes The image sizes attribute value.
		 * @param string $size  The image size data.
		 */
		$filter = static function ( $sizes, $size ) use ( $block ) {

			$id                       = isset( $block->attributes['id'] ) ? (int) $block->attributes['id'] : 0;
			$alignment                = $block->attributes['align'] ?? '';
			$width                    = isset( $block->attributes['width'] ) ? (int) $block->attributes['width'] : 0;
			$max_alignment            = $block->context['max_alignment'] ?? '';
			$container_relative_width = $block->context['container_relative_width'] ?? 1.0;

			/*
			 * Update width for cover block.
			 * See https://github.com/WordPress/gutenberg/blob/938720602082dc50a1746bd2e33faa3d3a6096d4/packages/block-library/src/cover/style.scss#L82-L87.
			 */
			if ( 'core/cover' === $block->name && in_array( $alignment, array( 'left', 'right' ), true ) ) {
				$size = array( 420, 420 );
			}

			$better_sizes = auto_sizes_calculate_better_sizes( $id, $size, $alignment, $width, $max_alignment, $container_relative_width );

			// If better sizes can't be calculated, use the default sizes.
			return false !== $better_sizes ? $better_sizes : $sizes;
		};

		// Hook this filter early, before default filters are run.
		add_filter( 'wp_calculate_image_sizes', $filter, 9, 2 );

		$sizes = wp_calculate_image_sizes(
			// If we don't have a size slug, assume the full size was used.
			$parsed_block['attrs']['sizeSlug'] ?? 'full',
			null,
			null,
			$parsed_block['attrs']['id'] ?? 0
		);

		remove_filter( 'wp_calculate_image_sizes', $filter, 9 );

		// Bail early if sizes are not calculated.
		if ( false === $sizes ) {
			return $content;
		}

		$processor->set_attribute( 'sizes', $sizes );

		return $processor->get_updated_html();
	}

	return $content;
}

/**
 * Modifies the sizes attribute of an image based on layout context.
 *
 * @since 1.4.0
 *
 * @param int                    $id                       The image attachment post ID.
 * @param string|array{int, int} $size                     Image size name or array of width and height.
 * @param string                 $align                    The image alignment.
 * @param int                    $resize_width             Resize image width.
 * @param string                 $max_alignment            The maximum usable layout alignment.
 * @param float                  $container_relative_width Container relative width.
 * @return string|false An improved sizes attribute or false if a better size cannot be calculated.
 */
function auto_sizes_calculate_better_sizes( int $id, $size, string $align, int $resize_width, string $max_alignment, float $container_relative_width ) {
	// Bail early if not a block theme.
	if ( ! wp_is_block_theme() ) {
		return false;
	}

	// Without an image ID or a resize width, we cannot calculate a better size.
	if ( 0 === $id && 0 === $resize_width ) {
		return false;
	}

	$image_data = wp_get_attachment_image_src( $id, $size );

	$image_width = false !== $image_data ? $image_data[1] : 0;

	// If we don't have an image width or a resize width, we cannot calculate a better size.
	if ( 0 === $image_width && 0 === $resize_width ) {
		return false;
	}

	/*
	 * If we don't have an image width, use the resize width.
	 * If we have both an image width and a resize width, use the smaller of the two.
	 */
	if ( 0 === $image_width ) {
		$image_width = $resize_width;
	} elseif ( 0 !== $resize_width ) {
		$image_width = min( $image_width, $resize_width );
	}

	// Normalize default alignment values.
	$align = '' !== $align ? $align : 'default';

	// Use the defined constant for constraints.
	$constraints = AUTO_SIZES_CONSTRAINTS;

	$alignment = $constraints[ $align ] > $constraints[ $max_alignment ] ? $align : $max_alignment;

	// Handle different alignment use cases.
	switch ( $alignment ) {
		case 'full':
			$layout_width = auto_sizes_get_layout_width( 'full' );
			break;

		case 'wide':
			$layout_width = auto_sizes_get_layout_width( 'wide' );
			// TODO: Add support for em, rem, vh, and vw.
			if (
				str_ends_with( $layout_width, 'px' ) &&
				( $container_relative_width > 0.0 ||
				$container_relative_width < 1.0 )
			) {
				// First remove 'px' from width.
				$layout_width = str_replace( 'px', '', $layout_width );
				// Convert to float for better precision.
				$layout_width = (float) $layout_width * $container_relative_width;
				$layout_width = sprintf( '%dpx', (int) $layout_width );
			}
			break;

		case 'left':
		case 'right':
		case 'center':
		default:
			$layout_alignment = in_array( $alignment, array( 'left', 'right' ), true ) ? 'wide' : 'default';
			$layout_width     = auto_sizes_get_layout_width( $layout_alignment );

			/*
			 * If the layout width is in pixels, we can compare against the image width
			 * on the server. Otherwise, we need to rely on CSS functions.
			 *
			 * TODO: Add support for em, rem, vh, and vw.
			 */
			if (
				str_ends_with( $layout_width, 'px' ) &&
				( $container_relative_width > 0.0 ||
				$container_relative_width < 1.0 )
			) {
				// First remove 'px' from width.
				$layout_width = str_replace( 'px', '', $layout_width );
				// Convert to float for better precision.
				$layout_width = (float) $layout_width * $container_relative_width;
				$layout_width = sprintf( '%dpx', min( (int) $layout_width, $image_width ) );
			} else {
				$layout_width = sprintf( 'min(%1$s, %2$spx)', $layout_width, $image_width );
			}

			break;
	}

	// Format layout width when not 'full'.
	if ( 'full' !== $alignment ) {
		$layout_width = sprintf( '(max-width: %1$s) 100vw, %1$s', $layout_width );
	}

	return $layout_width;
}

/**
 * Retrieves the layout width for an alignment defined in theme.json.
 *
 * @since 1.4.0
 *
 * @param string $alignment The alignment value.
 * @return string The alignment width based.
 */
function auto_sizes_get_layout_width( string $alignment ): string {
	$layout = wp_get_global_settings( array( 'layout' ) );

	$layout_widths = array(
		'full'    => '100vw', // Todo: incorporate useRootPaddingAwareAlignments.
		'wide'    => array_key_exists( 'wideSize', $layout ) ? $layout['wideSize'] : '',
		'default' => array_key_exists( 'contentSize', $layout ) ? $layout['contentSize'] : '',
	);

	return $layout_widths[ $alignment ] ?? '';
}

/**
 * Filters the context keys that a block type uses.
 *
 * @since 1.4.0
 *
 * @param string[]      $uses_context Array of registered uses context for a block type.
 * @param WP_Block_Type $block_type   The full block type object.
 * @return string[] The filtered context keys used by the block type.
 */
function auto_sizes_filter_uses_context( array $uses_context, WP_Block_Type $block_type ): array {
	// Define block-specific context usage.
	$block_specific_context = array(
		'core/cover'   => array( 'max_alignment', 'container_relative_width' ),
		'core/image'   => array( 'max_alignment', 'container_relative_width' ),
		'core/group'   => array( 'max_alignment' ),
		'core/columns' => array( 'max_alignment', 'container_relative_width' ),
		'core/column'  => array( 'max_alignment', 'column_count' ),
	);

	if ( isset( $block_specific_context[ $block_type->name ] ) ) {
		// Use array_values to reset array keys after merging.
		return array_values( array_unique( array_merge( $uses_context, $block_specific_context[ $block_type->name ] ) ) );
	}
	return $uses_context;
}

/**
 * Modifies the block context during rendering to blocks.
 *
 * @since 1.4.0
 *
 * @param array<string, mixed> $context      Current block context.
 * @param array<string, mixed> $block        The block being rendered.
 * @param WP_Block|null        $parent_block If this is a nested block, a reference to the parent block.
 * @return array<string, mixed> Modified block context.
 */
function auto_sizes_filter_render_block_context( array $context, array $block, ?WP_Block $parent_block ): array {
	// When no max alignment is set, the maximum is assumed to be 'full'.
	$context['max_alignment'] = $context['max_alignment'] ?? 'full';

	// The list of blocks that can modify outer layout context.
	$provider_blocks = array(
		'core/columns',
		'core/group',
	);

	if ( in_array( $block['blockName'], $provider_blocks, true ) ) {
		// Normalize default alignment values.
		$alignment = isset( $block['attrs']['align'] ) && '' !== $block['attrs']['align'] ? $block['attrs']['align'] : 'default';
		// Use the defined constant for constraints.
		$constraints = AUTO_SIZES_CONSTRAINTS;

		$context['max_alignment'] = $constraints[ $context['max_alignment'] ] > $constraints[ $alignment ] ? $context['max_alignment'] : $alignment;
	}

	if ( 'core/columns' === $block['blockName'] ) {
		// This is a special context key just to pass to the child 'core/column' block.
		$context['column_count'] = count( $block['innerBlocks'] );
	}

	if ( 'core/column' === $block['blockName'] ) {
		$found_image_block = wp_get_first_block( $block['innerBlocks'], 'core/image' );
		$found_cover_block = wp_get_first_block( $block['innerBlocks'], 'core/cover' );
		if ( count( $found_image_block ) > 0 || count( $found_cover_block ) > 0 ) {
			// Get column width, if explicitly set.
			if ( isset( $block['attrs']['width'] ) && '' !== $block['attrs']['width'] ) {
				$current_width = floatval( rtrim( $block['attrs']['width'], '%' ) ) / 100;
			} elseif ( isset( $parent_block->context['column_count'] ) && $parent_block->context['column_count'] ) {
				// Default to equally divided width if not explicitly set.
				$current_width = 1.0 / $parent_block->context['column_count'];
			} else {
				// Full width fallback.
				$current_width = 1.0;
			}

			// Multiply with parent's width if available.
			if (
				isset( $parent_block->context['container_relative_width'] ) &&
				( $current_width > 0.0 || $current_width < 1.0 )
			) {
				$context['container_relative_width'] = $parent_block->context['container_relative_width'] * $current_width;
			} else {
				$context['container_relative_width'] = $current_width;
			}
		}
	}
	return $context;
}
