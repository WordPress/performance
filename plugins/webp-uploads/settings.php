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
}
add_action( 'init', 'webp_uploads_register_media_settings_field' );

/**
 * Adds media settings field for the 'perflab_generate_webp_and_jpeg' setting.
 *
 * @since 1.0.0
 */
function webp_uploads_add_media_settings_field(): void {
	// Add settings field.
	add_settings_field(
		'perflab_generate_webp_and_jpeg',
		__( 'WebP and JPEG', 'webp-uploads' ),
		'webp_uploads_generate_webp_jpeg_setting_callback',
		'media',
		is_multisite() ? 'default' : 'uploads',
		array( 'class' => 'perflab-generate-webp-and-jpeg' )
	);
}
add_action( 'admin_init', 'webp_uploads_add_media_settings_field' );

/**
 * Renders the settings field for the 'perflab_generate_webp_and_jpeg' setting.
 *
 * @since 1.0.0
 */
function webp_uploads_generate_webp_jpeg_setting_callback(): void {
	if ( ! is_multisite() ) {
		?>
			</td>
			<td class="td-full">
		<?php
	}
	?>
		<label for="perflab_generate_webp_and_jpeg">
			<input name="perflab_generate_webp_and_jpeg" type="checkbox" id="perflab_generate_webp_and_jpeg" aria-describedby="perflab_generate_webp_and_jpeg_description" value="1"<?php checked( '1', get_option( 'perflab_generate_webp_and_jpeg' ) ); ?> />
			<?php esc_html_e( 'Generate JPEG files in addition to WebP', 'webp-uploads' ); ?>
		</label>
		<p class="description" id="perflab_generate_webp_and_jpeg_description"><?php esc_html_e( 'Enabling JPEG in addition to WebP can improve compatibility, but will effectively double the filesystem storage use of your images.', 'webp-uploads' ); ?></p>
	<?php
}

/**
 * Adds custom styles to hide specific elements in media settings.
 *
 * @since 1.0.0
 */
function webp_uploads_media_setting_style(): void {
	if ( is_multisite() ) {
		return;
	}
	?>
	<style>
		.form-table .perflab-generate-webp-and-jpeg th,
		.form-table .perflab-generate-webp-and-jpeg td:not(.td-full) {
			display: none;
		}
	</style>
	<?php
}
add_action( 'admin_head-options-media.php', 'webp_uploads_media_setting_style' );

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
