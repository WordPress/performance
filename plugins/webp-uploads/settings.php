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
 * Registers setting for generating both JPEG and WebP versions for image uploads.
 *
 * @since 1.0.0
 */
function webp_uploads_register_media_settings_field(): void {
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
			'default'      => current_theme_supports( 'html5', 'picture' ) ? true : false,
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
	// Add settings field.
	add_settings_field(
		'perflab_generate_webp_and_jpeg',
		__( 'WebP and JPEG', 'webp-uploads' ),
		'webp_uploads_generate_webp_jpeg_setting_callback',
		'media',
		is_multisite() ? 'default' : 'uploads',
		array( 'class' => 'perflab-generate-webp-and-jpeg' )
	);

	// Add settings field.
	add_settings_field(
		'webp_uploads_use_picture_element',
		__( 'Picture Element', 'webp-uploads' ),
		'webp_uploads_use_picture_element_callback',
		'media',
		is_multisite() ? 'default' : 'uploads',
		array( 'class' => 'webp-uploads-use-picture-element' )
	);
}
add_action( 'admin_init', 'webp_uploads_add_media_settings_fields' );

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
			<?php esc_html_e( 'Generate JPEG files in addition to WebP', 'webp-uploads' ); ?>
		</label>
		<p class="description" id="perflab_generate_webp_and_jpeg_description"><?php esc_html_e( 'Enabling JPEG in addition to WebP can improve compatibility, but will effectively double the filesystem storage use of your images.', 'webp-uploads' ); ?></p>
	</td></tr>
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
			<input name="webp_uploads_use_picture_element" type="checkbox" id="webp_uploads_use_picture_element" aria-describedby="webp_uploads_use_picture_element_description" value="1"<?php checked( '1', get_option( 'webp_uploads_use_picture_element' ) ); ?> />
			<?php esc_html_e( 'Use Picture Element', 'webp-uploads' ); ?>
		</label>
		<p class="description" id="webp_uploads_use_picture_element_description"><?php esc_html_e( 'Picture element serves AVIF or WebP with a fallback to JPEG handled by the browser automatically.', 'webp-uploads' ); ?></p>
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
