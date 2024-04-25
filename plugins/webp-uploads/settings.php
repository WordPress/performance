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
 * @since n.e.x.t The setting was made more general to coveroutputting JPEG as a secondary type. The "webp" option naming
 *        was left unchanged for backward compatibility.
 * @since n.e.x.t The `perflab_generate_avif_and_webp` was added to enable selecting between AVIF and WebP output.
 */
function webp_uploads_register_media_settings_field() {
	if ( wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ) ) {
		register_setting(
			'media',
			'perflab_generate_avif_and_webp',
			array(
				'type'         => 'string',
				'default'      => 'avif', // AVIF is the default if the editor supports it for new installs
				'show_in_rest' => false,
			)
		);
	}

	register_setting(
		'media',
		'perflab_generate_webp_and_jpeg',
		array(
			'type'         => 'boolean',
			'default'      => false,
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
function webp_uploads_add_media_settings_field() {

	// If AVIF is supported, add a dropdown to select the output format between AVIF and WebP.
	if ( wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ) ) {
		add_settings_field(
			'perflab_generate_avif_and_webp',
			__( 'Modern image format', 'webp-uploads' ),
			'webp_uploads_generate_avif_webp_setting_callback',
			'media',
			is_multisite() ? 'default' : 'uploads',
			array( 'class' => 'perflab-generate-avif-and-webp' )
		);

	}

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
add_action( 'admin_init', 'webp_uploads_add_media_settings_field' );

/**
 * Renders the settings field for the 'perflab_generate_avif_and_webp' setting.
 *
 * @since n.e.x.t
 */
function webp_uploads_generate_avif_webp_setting_callback() {

	$selected = get_option( 'perflab_generate_avif_and_webp' );
	?>
			<label for="perflab_generate_avif_and_webp">
				<select name="perflab_generate_avif_and_webp" id="perflab_generate_avif_and_webp" aria-describedby="perflab_generate_avif_and_webp_description">
					<option value="webp"<?php selected( 'webp', $selected ); ?>><?php esc_html_e( 'WebP', 'webp-uploads' ); ?></option>
					<option value="avif"<?php selected( 'avif', $selected ); ?>><?php esc_html_e( 'AVIF', 'webp-uploads' ); ?></option>
				</select>
				<?php esc_html_e( 'Generate images in this format', 'webp-uploads' ); ?>
			</label>
		<p class="description" id="perflab_generate_avif_and_webp_description"><?php esc_html_e( 'Select the format to use when generating new images from uploaded JPEGs.', 'webp-uploads' ); ?></p>
	<?php
}

/**
 * Renders the settings field for the 'perflab_generate_webp_and_jpeg' setting.
 *
 * @since 1.0.0
 */
function webp_uploads_generate_webp_jpeg_setting_callback() {

	?>

		<label for="perflab_generate_webp_and_jpeg">
			<input name="perflab_generate_webp_and_jpeg" type="checkbox" id="perflab_generate_webp_and_jpeg" aria-describedby="perflab_generate_webp_and_jpeg_description" value="1"<?php checked( '1', get_option( 'perflab_generate_webp_and_jpeg' ) ); ?> />
			<?php esc_html_e( 'Output JPEG images in addition to the modern format', 'webp-uploads' ); ?>
		</label>
		<p class="description" id="perflab_generate_webp_and_jpeg_description"><?php esc_html_e( 'Enabling JPEG in addition to AVIF or WebP can improve compatibility, but will increase the filesystem storage use of your images.', 'webp-uploads' ); ?></p>
	<?php
}

/**
 * Adds a settings link to the plugin's action links.
 *
 * @since 1.1.0
 * @since n.e.x.t Renamed from webp_uploads_settings_link() to webp_uploads_add_settings_action_link()
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
