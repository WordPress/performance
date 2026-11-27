<?php
/**
 * Abilities API registrations for Enhanced Responsive Images.
 *
 * @package auto-sizes
 *
 * @since n.e.x.t
 */

declare( strict_types = 1 );

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Registers the shared Performance Lab images ability category if not already registered.
 *
 * @since n.e.x.t
 */
function auto_sizes_register_ability_category(): void {
	if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( 'performance-lab-images' ) ) {
		return;
	}

	wp_register_ability_category(
		'performance-lab-images',
		array(
			'label'       => __( 'Performance Lab: Images', 'auto-sizes' ),
			'description' => __( 'Abilities for inspecting and optimizing image attachments and placeholders.', 'auto-sizes' ),
		)
	);
}
add_action( 'wp_abilities_api_categories_init', 'auto_sizes_register_ability_category' );

/**
 * Registers the compute-sizes-attribute ability for Enhanced Responsive Images.
 *
 * @since n.e.x.t
 */
function auto_sizes_register_compute_sizes_ability(): void {
	$valid_alignments = array_keys( AUTO_SIZES_CONSTRAINTS );

	wp_register_ability(
		'auto-sizes/compute-sizes-attribute',
		array(
			'label'               => __( 'Compute Image Sizes Attribute', 'auto-sizes' ),
			'description'         => __( 'Computes the improved HTML sizes attribute value that the plugin would emit for an image given its block alignment, resize width, and ancestor layout context.', 'auto-sizes' ),
			'category'            => 'performance-lab-images',
			'execute_callback'    => 'auto_sizes_ability_compute_sizes_attribute',
			'permission_callback' => 'auto_sizes_ability_permission_check',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'attachment_id'            => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'default'     => 0,
						'description' => __( 'The image attachment post ID (0 if unknown, in which case resize_width is required).', 'auto-sizes' ),
					),
					'size'                     => array(
						'type'        => 'string',
						'default'     => 'full',
						'description' => __( 'Registered WordPress image size name (e.g. full, large, medium).', 'auto-sizes' ),
					),
					'align'                    => array(
						'type'        => 'string',
						'enum'        => $valid_alignments,
						'default'     => 'default',
						'description' => __( 'The image block alignment.', 'auto-sizes' ),
					),
					'resize_width'             => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'default'     => 0,
						'description' => __( 'Explicitly resized width in pixels (0 if not resized).', 'auto-sizes' ),
					),
					'max_alignment'            => array(
						'type'        => 'string',
						'enum'        => $valid_alignments,
						'default'     => 'full',
						'description' => __( 'Maximum alignment allowed by ancestor container blocks.', 'auto-sizes' ),
					),
					'container_relative_width' => array(
						'type'             => 'number',
						'exclusiveMinimum' => 0,
						'maximum'          => 1,
						'default'          => 1.0,
						'description'      => __( 'Width of the ancestor container relative to its parent (between 0 and 1, e.g. 0.5 for a 50% column).', 'auto-sizes' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'sizes'                    => array(
						'type'        => array( 'string', 'null' ),
						'description' => __( 'Computed sizes attribute value, or null if the plugin cannot compute a better value.', 'auto-sizes' ),
					),
					'is_block_theme'           => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the active theme is a block theme (required for calculation).', 'auto-sizes' ),
					),
					'attachment_id'            => array(
						'type'        => 'integer',
						'description' => __( 'The attachment post ID used for calculation.', 'auto-sizes' ),
					),
					'size'                     => array(
						'type'        => 'string',
						'description' => __( 'The image size slug used for calculation.', 'auto-sizes' ),
					),
					'align'                    => array(
						'type'        => 'string',
						'description' => __( 'The image alignment used for calculation.', 'auto-sizes' ),
					),
					'resize_width'             => array(
						'type'        => 'integer',
						'description' => __( 'The resize width in pixels used for calculation.', 'auto-sizes' ),
					),
					'max_alignment'            => array(
						'type'        => 'string',
						'description' => __( 'The maximum ancestor alignment used for calculation.', 'auto-sizes' ),
					),
					'container_relative_width' => array(
						'type'        => 'number',
						'description' => __( 'The container relative width factor used for calculation.', 'auto-sizes' ),
					),
				),
			),
			'meta'                => array(
				'show_in_rest' => true,
				'mcp'          => array(
					'public' => true,
				),
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);
}
add_action( 'wp_abilities_api_init', 'auto_sizes_register_compute_sizes_ability' );

/**
 * Registers the get-version-status ability for Enhanced Responsive Images.
 *
 * @since n.e.x.t
 */
function auto_sizes_register_get_version_status_ability(): void {
	wp_register_ability(
		'auto-sizes/get-version-status',
		array(
			'label'               => __( 'Get Auto-Sizes Version Status', 'auto-sizes' ),
			'description'         => __( 'Reports the Enhanced Responsive Images plugin version, WordPress core sizes=auto support status, and active theme layout widths.', 'auto-sizes' ),
			'category'            => 'performance-lab-images',
			'execute_callback'    => 'auto_sizes_ability_get_version_status',
			'permission_callback' => 'auto_sizes_ability_permission_check',
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'plugin_version'            => array(
						'type'        => 'string',
						'description' => __( 'Installed Enhanced Responsive Images plugin version.', 'auto-sizes' ),
					),
					'wp_version'                => array(
						'type'        => 'string',
						'description' => __( 'Current WordPress core version.', 'auto-sizes' ),
					),
					'core_auto_sizes_supported' => array(
						'type'        => 'boolean',
						'description' => __( 'Whether WordPress core natively handles sizes="auto" (WordPress 6.7+).', 'auto-sizes' ),
					),
					'polyfill_active'           => array(
						'type'        => 'boolean',
						'description' => __( 'Whether a legacy sizes="auto" polyfill is active (always false on WordPress 6.9+).', 'auto-sizes' ),
					),
					'is_block_theme'            => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the active theme is a block theme (required for improved sizes calculation).', 'auto-sizes' ),
					),
					'layout'                    => array(
						'type'       => 'object',
						'properties' => array(
							'content_size' => array(
								'type'        => 'string',
								'description' => __( 'The theme.json layout contentSize value.', 'auto-sizes' ),
							),
							'wide_size'    => array(
								'type'        => 'string',
								'description' => __( 'The theme.json layout wideSize value.', 'auto-sizes' ),
							),
						),
					),
				),
			),
			'meta'                => array(
				'show_in_rest' => true,
				'mcp'          => array(
					'public' => true,
				),
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);
}
add_action( 'wp_abilities_api_init', 'auto_sizes_register_get_version_status_ability' );

/**
 * Checks whether the current user has permission to invoke diagnostic auto-sizes abilities.
 *
 * @since n.e.x.t
 *
 * @return bool Whether the current user can edit posts.
 */
function auto_sizes_ability_permission_check(): bool {
	return current_user_can( 'edit_posts' );
}

/**
 * Executes the auto-sizes/compute-sizes-attribute ability.
 *
 * @since n.e.x.t
 *
 * @param array<string, mixed>|mixed $input Ability input.
 * @return array{
 *     sizes: string|null,
 *     is_block_theme: bool,
 *     attachment_id: non-negative-int,
 *     size: string,
 *     align: string,
 *     resize_width: non-negative-int,
 *     max_alignment: string,
 *     container_relative_width: float
 * }|WP_Error Computed sizes result or WP_Error on invalid input.
 */
function auto_sizes_ability_compute_sizes_attribute( $input = null ) {
	if ( ! is_array( $input ) ) {
		return new WP_Error(
			'auto_sizes_invalid_input',
			__( 'Input must be an object with attachment_id or resize_width.', 'auto-sizes' ),
			array( 'status' => 400 )
		);
	}

	// Apply schema defaults explicitly as WP Abilities API does not inject property defaults.
	$attachment_id = isset( $input['attachment_id'] ) && is_int( $input['attachment_id'] ) ? $input['attachment_id'] : 0;
	if ( $attachment_id < 0 ) {
		return new WP_Error(
			'auto_sizes_invalid_attachment_id',
			__( 'attachment_id must be a non-negative integer.', 'auto-sizes' ),
			array( 'status' => 400 )
		);
	}

	$resize_width = isset( $input['resize_width'] ) && is_int( $input['resize_width'] ) ? $input['resize_width'] : 0;
	if ( $resize_width < 0 ) {
		return new WP_Error(
			'auto_sizes_invalid_resize_width',
			__( 'resize_width must be a non-negative integer.', 'auto-sizes' ),
			array( 'status' => 400 )
		);
	}

	if ( 0 === $attachment_id && 0 === $resize_width ) {
		return new WP_Error(
			'auto_sizes_missing_dimensions',
			__( 'Either a positive attachment_id or a positive resize_width is required.', 'auto-sizes' ),
			array( 'status' => 400 )
		);
	}

	$size = isset( $input['size'] ) && is_string( $input['size'] ) && '' !== $input['size'] ? $input['size'] : 'full';

	$align = isset( $input['align'] ) && is_string( $input['align'] ) && '' !== $input['align'] ? $input['align'] : 'default';
	if ( ! array_key_exists( $align, AUTO_SIZES_CONSTRAINTS ) ) {
		return new WP_Error(
			'auto_sizes_invalid_alignment',
			__( 'Invalid align value.', 'auto-sizes' ),
			array( 'status' => 400 )
		);
	}

	$max_alignment = isset( $input['max_alignment'] ) && is_string( $input['max_alignment'] ) && '' !== $input['max_alignment'] ? $input['max_alignment'] : 'full';
	if ( ! array_key_exists( $max_alignment, AUTO_SIZES_CONSTRAINTS ) ) {
		return new WP_Error(
			'auto_sizes_invalid_max_alignment',
			__( 'Invalid max_alignment value.', 'auto-sizes' ),
			array( 'status' => 400 )
		);
	}

	$container_relative_width = 1.0;
	if ( isset( $input['container_relative_width'] ) && ( is_float( $input['container_relative_width'] ) || is_int( $input['container_relative_width'] ) ) ) {
		$container_relative_width = (float) $input['container_relative_width'];
		if ( $container_relative_width <= 0.0 || $container_relative_width > 1.0 ) {
			return new WP_Error(
				'auto_sizes_invalid_container_relative_width',
				__( 'container_relative_width must be greater than 0 and at most 1.', 'auto-sizes' ),
				array( 'status' => 400 )
			);
		}
	}

	$sizes = auto_sizes_calculate_better_sizes(
		$attachment_id,
		$size,
		$align,
		$resize_width,
		$max_alignment,
		$container_relative_width
	);

	return array(
		'sizes'                    => false !== $sizes ? $sizes : null,
		'is_block_theme'           => wp_is_block_theme(),
		'attachment_id'            => $attachment_id,
		'size'                     => $size,
		'align'                    => $align,
		'resize_width'             => $resize_width,
		'max_alignment'            => $max_alignment,
		'container_relative_width' => $container_relative_width,
	);
}

/**
 * Executes the auto-sizes/get-version-status ability.
 *
 * @since n.e.x.t
 *
 * @return array{
 *     plugin_version: string,
 *     wp_version: string,
 *     core_auto_sizes_supported: bool,
 *     polyfill_active: bool,
 *     is_block_theme: bool,
 *     layout: array{ content_size: string, wide_size: string }
 * } Version and theme layout status.
 */
function auto_sizes_ability_get_version_status(): array {
	return array(
		'plugin_version'            => IMAGE_AUTO_SIZES_VERSION,
		'wp_version'                => get_bloginfo( 'version' ),
		'core_auto_sizes_supported' => function_exists( 'wp_img_tag_add_auto_sizes' ),
		'polyfill_active'           => false,
		'is_block_theme'            => wp_is_block_theme(),
		'layout'                    => array(
			'content_size' => auto_sizes_get_layout_width( 'default' ),
			'wide_size'    => auto_sizes_get_layout_width( 'wide' ),
		),
	);
}
