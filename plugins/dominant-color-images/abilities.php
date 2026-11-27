<?php
/**
 * Abilities API registrations for Image Placeholders.
 *
 * @package dominant-color-images
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
function dominant_color_register_ability_category(): void {
	if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( 'performance-lab-images' ) ) {
		return;
	}

	wp_register_ability_category(
		'performance-lab-images',
		array(
			'label'       => __( 'Performance Lab: Images', 'dominant-color-images' ),
			'description' => __( 'Abilities for inspecting and optimizing image attachments and placeholders.', 'dominant-color-images' ),
		)
	);
}
add_action( 'wp_abilities_api_categories_init', 'dominant_color_register_ability_category' );

/**
 * Registers abilities for the Image Placeholders plugin.
 *
 * @since n.e.x.t
 */
function dominant_color_register_abilities(): void {
	wp_register_ability(
		'dominant-color-images/get-attachment-color',
		array(
			'label'               => __( 'Get Attachment Dominant Color', 'dominant-color-images' ),
			'description'         => __( 'Returns the dominant color hex value and transparency flag stored for an image attachment.', 'dominant-color-images' ),
			'category'            => 'performance-lab-images',
			'execute_callback'    => 'dominant_color_ability_get_attachment_color',
			'permission_callback' => 'dominant_color_ability_get_attachment_color_permission',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'attachment_id' ),
				'additionalProperties' => false,
				'properties'           => array(
					'attachment_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'The attachment post ID.', 'dominant-color-images' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'attachment_id'    => array(
						'type'        => 'integer',
						'description' => __( 'The attachment post ID.', 'dominant-color-images' ),
					),
					'dominant_color'   => array(
						'type'        => array( 'string', 'null' ),
						'description' => __( 'Six-character lowercase hex color without leading #, or null if unavailable.', 'dominant-color-images' ),
					),
					'has_transparency' => array(
						'type'        => array( 'boolean', 'null' ),
						'description' => __( 'Whether the image has transparency, or null if unavailable.', 'dominant-color-images' ),
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
add_action( 'wp_abilities_api_init', 'dominant_color_register_abilities' );

/**
 * Registers the compute-attachment-color ability for the Image Placeholders plugin.
 *
 * @since n.e.x.t
 */
function dominant_color_register_compute_ability(): void {
	wp_register_ability(
		'dominant-color-images/compute-attachment-color',
		array(
			'label'               => __( 'Compute Attachment Dominant Color', 'dominant-color-images' ),
			'description'         => __( 'Computes the dominant color and transparency of an image attachment on the fly without storing the result. Useful for previewing or debugging.', 'dominant-color-images' ),
			'category'            => 'performance-lab-images',
			'execute_callback'    => 'dominant_color_ability_compute_attachment_color',
			'permission_callback' => 'dominant_color_ability_compute_attachment_color_permission',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'attachment_id' ),
				'additionalProperties' => false,
				'properties'           => array(
					'attachment_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'The attachment post ID.', 'dominant-color-images' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'attachment_id'    => array(
						'type'        => 'integer',
						'description' => __( 'The attachment post ID.', 'dominant-color-images' ),
					),
					'dominant_color'   => array(
						'type'        => 'string',
						'description' => __( 'Six-character lowercase hex color without leading #.', 'dominant-color-images' ),
					),
					'has_transparency' => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the image has transparency.', 'dominant-color-images' ),
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
add_action( 'wp_abilities_api_init', 'dominant_color_register_compute_ability' );

/**
 * Registers the regenerate-attachment-color ability for the Image Placeholders plugin.
 *
 * @since n.e.x.t
 */
function dominant_color_register_regenerate_ability(): void {
	wp_register_ability(
		'dominant-color-images/regenerate-attachment-color',
		array(
			'label'               => __( 'Regenerate Attachment Dominant Color', 'dominant-color-images' ),
			'description'         => __( 'Recomputes and persists the dominant color and transparency for an image attachment. Use after the original image has been replaced or edited.', 'dominant-color-images' ),
			'category'            => 'performance-lab-images',
			'execute_callback'    => 'dominant_color_ability_regenerate_attachment_color',
			'permission_callback' => 'dominant_color_ability_regenerate_attachment_color_permission',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'attachment_id' ),
				'additionalProperties' => false,
				'properties'           => array(
					'attachment_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'The attachment post ID.', 'dominant-color-images' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'attachment_id'    => array(
						'type'        => 'integer',
						'description' => __( 'The attachment post ID.', 'dominant-color-images' ),
					),
					'dominant_color'   => array(
						'type'        => 'string',
						'description' => __( 'Six-character lowercase hex color without leading #.', 'dominant-color-images' ),
					),
					'has_transparency' => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the image has transparency.', 'dominant-color-images' ),
					),
				),
			),
			'meta'                => array(
				'show_in_rest' => true,
				'mcp'          => array(
					'public' => true,
				),
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		)
	);
}
add_action( 'wp_abilities_api_init', 'dominant_color_register_regenerate_ability' );

/**
 * Checks whether the current user can read the attachment's dominant color.
 *
 * @since n.e.x.t
 *
 * @param array<string, mixed>|mixed $input Ability input.
 * @return bool Whether the current user has permission.
 */
function dominant_color_ability_get_attachment_color_permission( $input = null ): bool {
	if ( ! is_array( $input ) || ! isset( $input['attachment_id'] ) || ! is_int( $input['attachment_id'] ) || $input['attachment_id'] < 1 ) {
		return current_user_can( 'read' );
	}

	if ( get_post( $input['attachment_id'] ) instanceof WP_Post ) {
		return current_user_can( 'read_post', $input['attachment_id'] );
	}

	return current_user_can( 'read' );
}

/**
 * Executes the dominant-color-images/get-attachment-color ability.
 *
 * @since n.e.x.t
 *
 * @param array<string, mixed>|mixed $input Ability input.
 * @return array{ attachment_id: positive-int, dominant_color: non-empty-string|null, has_transparency: bool|null }|WP_Error Result data or WP_Error on invalid input.
 */
function dominant_color_ability_get_attachment_color( $input = null ) {
	if ( ! is_array( $input ) || ! isset( $input['attachment_id'] ) || ! is_int( $input['attachment_id'] ) || $input['attachment_id'] < 1 ) {
		return new WP_Error(
			'dominant_color_images_missing_attachment_id',
			__( 'A valid positive integer attachment_id is required.', 'dominant-color-images' ),
			array( 'status' => 400 )
		);
	}

	$attachment_id = $input['attachment_id'];

	if ( ! wp_attachment_is_image( $attachment_id ) ) {
		return new WP_Error(
			'dominant_color_images_invalid_attachment',
			__( 'The specified ID is not a valid image attachment.', 'dominant-color-images' ),
			array( 'status' => 404 )
		);
	}

	return array(
		'attachment_id'    => $attachment_id,
		'dominant_color'   => dominant_color_get_dominant_color( $attachment_id ),
		'has_transparency' => dominant_color_has_transparency( $attachment_id ),
	);
}

/**
 * Checks whether the current user can compute an attachment's dominant color.
 *
 * @since n.e.x.t
 *
 * @param array<string, mixed>|mixed $input Ability input.
 * @return bool Whether the current user has permission.
 */
function dominant_color_ability_compute_attachment_color_permission( $input = null ): bool {
	if ( ! is_array( $input ) || ! isset( $input['attachment_id'] ) || ! is_int( $input['attachment_id'] ) || $input['attachment_id'] < 1 ) {
		return current_user_can( 'edit_posts' );
	}

	return current_user_can( 'edit_post', $input['attachment_id'] );
}

/**
 * Executes the dominant-color-images/compute-attachment-color ability.
 *
 * Computes the dominant color and transparency on the fly without persisting.
 *
 * @since n.e.x.t
 *
 * @param array<string, mixed>|mixed $input Ability input.
 * @return array{ attachment_id: positive-int, dominant_color: string, has_transparency: bool }|WP_Error Result data or WP_Error on failure.
 */
function dominant_color_ability_compute_attachment_color( $input = null ) {
	if ( ! is_array( $input ) || ! isset( $input['attachment_id'] ) || ! is_int( $input['attachment_id'] ) || $input['attachment_id'] < 1 ) {
		return new WP_Error(
			'dominant_color_images_missing_attachment_id',
			__( 'A valid positive integer attachment_id is required.', 'dominant-color-images' ),
			array( 'status' => 400 )
		);
	}

	$attachment_id = $input['attachment_id'];

	$color_data = dominant_color_get_dominant_color_data( $attachment_id );
	if ( is_wp_error( $color_data ) ) {
		return $color_data;
	}

	return array(
		'attachment_id'    => $attachment_id,
		'dominant_color'   => isset( $color_data['dominant_color'] ) ? $color_data['dominant_color'] : '',
		'has_transparency' => isset( $color_data['has_transparency'] ) ? $color_data['has_transparency'] : false,
	);
}

/**
 * Checks whether the current user can regenerate an attachment's dominant color.
 *
 * @since n.e.x.t
 *
 * @param array<string, mixed>|mixed $input Ability input.
 * @return bool Whether the current user has permission.
 */
function dominant_color_ability_regenerate_attachment_color_permission( $input = null ): bool {
	if ( ! is_array( $input ) || ! isset( $input['attachment_id'] ) || ! is_int( $input['attachment_id'] ) || $input['attachment_id'] < 1 ) {
		return current_user_can( 'edit_posts' );
	}

	return current_user_can( 'edit_post', $input['attachment_id'] );
}

/**
 * Executes the dominant-color-images/regenerate-attachment-color ability.
 *
 * Recomputes the dominant color and transparency and persists them in
 * attachment metadata.
 *
 * @since n.e.x.t
 *
 * @param array<string, mixed>|mixed $input Ability input.
 * @return array{ attachment_id: positive-int, dominant_color: string, has_transparency: bool }|WP_Error Result data or WP_Error on failure.
 */
function dominant_color_ability_regenerate_attachment_color( $input = null ) {
	if ( ! is_array( $input ) || ! isset( $input['attachment_id'] ) || ! is_int( $input['attachment_id'] ) || $input['attachment_id'] < 1 ) {
		return new WP_Error(
			'dominant_color_images_missing_attachment_id',
			__( 'A valid positive integer attachment_id is required.', 'dominant-color-images' ),
			array( 'status' => 400 )
		);
	}

	$attachment_id = $input['attachment_id'];

	$color_data = dominant_color_get_dominant_color_data( $attachment_id );
	if ( is_wp_error( $color_data ) ) {
		return $color_data;
	}

	$image_meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_array( $image_meta ) ) {
		$image_meta = array();
	}

	if ( isset( $color_data['dominant_color'] ) ) {
		$image_meta['dominant_color'] = $color_data['dominant_color'];
	}
	if ( isset( $color_data['has_transparency'] ) ) {
		$image_meta['has_transparency'] = $color_data['has_transparency'];
	}

	wp_update_attachment_metadata( $attachment_id, $image_meta );

	return array(
		'attachment_id'    => $attachment_id,
		'dominant_color'   => isset( $color_data['dominant_color'] ) ? $color_data['dominant_color'] : '',
		'has_transparency' => isset( $color_data['has_transparency'] ) ? $color_data['has_transparency'] : false,
	);
}
