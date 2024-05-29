<?php
/**
 * Settings for the Modern Image Formats plugin.
 *
 * @package webp-uploads
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers setting for generating JPEG in addition to the selected modern format for image uploads.
 *
 * @since 1.0.0
 * @since n.e.x.t The setting was made more general to cover outputting JPEG as a secondary type. The "webp" option naming
 *                was left unchanged for backward compatibility.
 * @since n.e.x.t The `perflab_modern_image_format` was added to enable selecting an output format.
 *                Currently includes AVIF and WebP.
 */
function webp_uploads_register_media_settings_field(): void {
	register_setting(
		'media',
		'perflab_modern_image_format',
		array(
			'sanitize_callback' => 'webp_uploads_sanitize_image_format',
			'type'              => 'string',
			'default'           => 'avif',                                                                                                                                    // AVIF is the default if the editor supports it.
			'show_in_rest'      => false,
		)
	);

	register_setting(
		'media',
		'perflab_generate_webp_and_jpeg',
		array(
			'type'         => 'boolean',
			'default'      => false,
			'show_in_rest' => false,
		)
	);
	// Add a setting to use the picture element.
	register_setting(
		'media',
		'webp_uploads_use_picture_element',
		array(
			'type'         => 'boolean',
			// Use picture element by default if the theme declares support for it.
			'default'      => current_theme_supports( 'html5', 'picture' ),
			'show_in_rest' => false,
		)
	);
}
add_action( 'init', 'webp_uploads_register_media_settings_field' );

/**
 * Adds media settings field for the 'perflab_generate_webp_and_jpeg' setting.
 *
 * @since 1.0.0
 */
function webp_uploads_add_media_settings_fields(): void {

	// Add a dropdown to select the output format between AVIF and WebP output.
	add_settings_field(
		'perflab_modern_image_format',
		__( 'Modern image format', 'webp-uploads' ),
		'webp_uploads_generate_avif_webp_setting_callback',
		'media',
		is_multisite() ? 'default' : 'uploads',
		array( 'class' => 'perflab-generate-avif-and-webp' )
	);

	// Add settings field.
	add_settings_field(
		'perflab_generate_webp_and_jpeg',
		__( 'Also output JPEG', 'webp-uploads' ),
		'webp_uploads_generate_webp_jpeg_setting_callback',
		'media',
		is_multisite() ? 'default' : 'uploads',
		array( 'class' => 'perflab-generate-webp-and-jpeg' )
	);
}
add_action( 'admin_init', 'webp_uploads_add_media_settings_fields' );

/**
 * Renders the settings field for the 'perflab_modern_image_format' setting.
 *
 * @since n.e.x.t
 */
function webp_uploads_generate_avif_webp_setting_callback(): void {

	$selected       = webp_uploads_get_image_output_format();
	$avif_supported = webp_uploads_mime_type_supported( 'image/avif' );
	// Ensure WebP selected if AVIF is not supported.
	if ( ! $avif_supported ) {
		$selected = 'webp';
	}
	?>
	<select name="perflab_modern_image_format" id="perflab_modern_image_format" aria-describedby="perflab_modern_image_format_description">
		<option value="webp"<?php selected( 'webp', $selected ); ?>><?php esc_html_e( 'WebP', 'webp-uploads' ); ?></option>
		<option value="avif"<?php selected( 'avif', $selected ); ?><?php disabled( ! $avif_supported ); ?>><?php esc_html_e( 'AVIF', 'webp-uploads' ); ?></option>
	</select>
	<label for="perflab_modern_image_format">
		<?php esc_html_e( 'Generate images in this format', 'webp-uploads' ); ?>
	</label>
	<p class="description" id="perflab_modern_image_format_description"><?php esc_html_e( 'Select the format to use when generating new images from uploaded JPEGs.', 'webp-uploads' ); ?></p>
	<?php if ( ! $avif_supported ) : ?>
		<br />
		<div class="notice notice-warning is-dismissible inline">
			<p><b><?php esc_html_e( 'AVIF support is not available.', 'webp-uploads' ); ?></b></p>
			<p><?php esc_html_e( 'AVIF support can only be enabled by your hosting provider, so contact them for more information.', 'webp-uploads' ); ?></p>
		</div>
	<?php endif; ?>
	<?php
}

/**
 * Renders the settings field for the 'perflab_generate_webp_and_jpeg' setting.
 *
 * @since 1.0.0
 */
function webp_uploads_generate_webp_jpeg_setting_callback(): void {

	?>
	<tr><td colspan="2" class="td-full">
		<label for="perflab_generate_webp_and_jpeg">
			<input name="perflab_generate_webp_and_jpeg" type="checkbox" id="perflab_generate_webp_and_jpeg" aria-describedby="perflab_generate_webp_and_jpeg_description" value="1"<?php checked( '1', get_option( 'perflab_generate_webp_and_jpeg' ) ); ?> />
			<?php esc_html_e( 'Output JPEG images in addition to the modern format', 'webp-uploads' ); ?>
		</label>
		<p class="description" id="perflab_generate_webp_and_jpeg_description"><?php esc_html_e( 'Enabling JPEG output can improve compatibility, but will increase the filesystem storage use of your images.', 'webp-uploads' ); ?></p>
	<?php
}

/**
 * Renders the settings field for the 'webp_uploads_use_picture_element' setting.
 *
 * @since 1.0.0
 */
function webp_uploads_use_picture_element_callback(): void {
	?>
		<tr><td colspan="2" class="td-full">
			<label for="webp_uploads_use_picture_element">
			<input name="webp_uploads_use_picture_element" type="checkbox" id="webp_uploads_use_picture_element" aria-describedby="webp_uploads_use_picture_element_description" value="1"<?php checked( webp_uploads_is_picture_element_enabled() ); ?> />
			<?php esc_html_e( 'Use `<picture>` Element', 'webp-uploads' ); ?>
		</label>
		<p class="description" id="webp_uploads_use_picture_element_description"><?php esc_html_e( 'The picture element serves a modern image format with a fallback to JPEG. Warning: Make sure you test your theme and plugins for compatibility. In particular, CSS selectors will not match images when using the child combinator (e.g. figure > img).', 'webp-uploads' ); ?></p>
		</td></tr>
	<?php
}

/**
 * Adds a settings link to the plugin's action links.
 *
 * @since 1.1.0
 * @since 1.1.1 Renamed from webp_uploads_settings_link() to webp_uploads_add_settings_action_link()
 *
 * @param string[]|mixed $links An array of plugin action links.
 * @return string[]|mixed The modified list of actions.
 */
function webp_uploads_add_settings_action_link( $links ) {
	if ( ! is_array( $links ) ) {
		return $links;
	}

	$settings_link = sprintf(
		'<a href="%1$s">%2$s</a>',
		esc_url( admin_url( 'options-media.php#perflab_generate_webp_and_jpeg' ) ),
		esc_html__( 'Settings', 'webp-uploads' )
	);

	return array_merge(
		array( 'settings' => $settings_link ),
		$links
	);
}
add_filter( 'plugin_action_links_' . WEBP_UPLOADS_MAIN_FILE, 'webp_uploads_add_settings_action_link' );
