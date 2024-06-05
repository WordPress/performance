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
			'default'      => current_theme_supports( 'html5', 'picture' ), // Use picture element by default if the theme declares support for it.
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
			'default'      => current_theme_supports( 'html5', 'picture' ), // Use picture element by default if the theme declares support for it.
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
	add_settings_section(
		'perflab_modern_image_format_settings',
		__( 'Modern Image Formats', 'webp-uploads' ),
		'__return_empty_string',
		'media',
		array(
			'before_section' => '<div id="modern-image-formats">',
			'after_section'  => '</div>',
		)
	);

	// Add a dropdown to select the output format between AVIF and WebP output.
	add_settings_field(
		'perflab_modern_image_format',
		__( 'Image output format', 'webp-uploads' ),
		'webp_uploads_generate_avif_webp_setting_callback',
		'media',
		'perflab_modern_image_format_settings',
		array( 'class' => 'perflab-generate-avif-and-webp' )
	);

	// Only add the remaining settings fields if at least one modern image format is supported.
	if ( ! webp_uploads_mime_type_supported( 'image/avif' ) && ! webp_uploads_mime_type_supported( 'image/webp' ) ) {
		return;
	}

	// Add JPEG Output settings field.
	add_settings_field(
		'perflab_generate_webp_and_jpeg',
		__( 'Also output JPEG', 'webp-uploads' ),
		'webp_uploads_generate_webp_jpeg_setting_callback',
		'media',
		'perflab_modern_image_format_settings',
		array( 'class' => 'perflab-generate-webp-and-jpeg' )
	);

	// Add picture element support settings field.
	add_settings_field(
		'webp_uploads_use_picture_element',
		__( 'Picture element', 'webp-uploads' ),
		'webp_uploads_use_picture_element_callback',
		'media',
		'perflab_modern_image_format_settings',
		array( 'class' => 'webp-uploads-use-picture-element' )
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
	$webp_supported = webp_uploads_mime_type_supported( 'image/webp' );

	// If neither format is support, the entire field is not shown.
	if ( ! $avif_supported && ! $webp_supported ) {
		?>
		<br />
		<div class="notice notice-warning inline">
			<p><b><?php esc_html_e( 'Modern image support is not available.', 'webp-uploads' ); ?></b></p>
			<p><?php esc_html_e( 'WebP or AVIF support can only be enabled by your hosting provider, so contact them for more information.', 'webp-uploads' ); ?></p>
		</div>
		<?php
		return;
	}

	// If only one of the two formats is supported, the dropdown defaults to that type and the other type is disabled.
	if ( ! $avif_supported && 'avif' === $selected ) {
		$selected = 'webp';
	} elseif ( ! $webp_supported && 'webp' === $selected ) {
		$selected = 'avif';
	}
	?>
	<select name="perflab_modern_image_format" id="perflab_modern_image_format" aria-describedby="perflab_modern_image_format_description">
		<option value="webp"<?php selected( 'webp', $selected ); ?><?php disabled( ! $webp_supported ); ?>><?php esc_html_e( 'WebP', 'webp-uploads' ); ?></option>
		<option value="avif"<?php selected( 'avif', $selected ); ?><?php disabled( ! $avif_supported ); ?>><?php esc_html_e( 'AVIF', 'webp-uploads' ); ?></option>
	</select>
	<label for="perflab_modern_image_format">
		<?php esc_html_e( 'Generate images in this format', 'webp-uploads' ); ?>
	</label>
	<p class="description" id="perflab_modern_image_format_description"><?php esc_html_e( 'Select the format to use when generating new images from uploaded JPEGs.', 'webp-uploads' ); ?></p>
	<?php if ( ! $avif_supported ) : ?>
		<br />
		<div class="notice notice-warning inline">
			<p><b><?php esc_html_e( 'AVIF support is not available.', 'webp-uploads' ); ?></b></p>
			<p><?php esc_html_e( 'AVIF support can only be enabled by your hosting provider, so contact them for more information.', 'webp-uploads' ); ?></p>
		</div>
	<?php endif; ?>
	<?php if ( ! $webp_supported ) : ?>
		<br />
		<div class="notice notice-warning inline">
			<p><b><?php esc_html_e( 'WebP support is not available.', 'webp-uploads' ); ?></b></p>
			<p><?php esc_html_e( 'WebP support can only be enabled by your hosting provider, so contact them for more information.', 'webp-uploads' ); ?></p>
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
		<label for="perflab_generate_webp_and_jpeg">
			<input name="perflab_generate_webp_and_jpeg" type="checkbox" id="perflab_generate_webp_and_jpeg" aria-describedby="perflab_generate_webp_and_jpeg_description" value="1"<?php checked( '1', get_option( 'perflab_generate_webp_and_jpeg' ) ); ?> />
			<?php esc_html_e( 'Output JPEG images in addition to the modern format', 'webp-uploads' ); ?>
		</label>
		<p class="description" id="perflab_generate_webp_and_jpeg_description"><?php esc_html_e( 'Enabling JPEG output can improve compatibility, but will increase the filesystem storage use of your images.', 'webp-uploads' ); ?></p>
		<script>
			// Listen for clicks on the JPEG output checkbox, enabling/disabling the
			// picture element checkbox accordingly.
			document.getElementById( 'perflab_generate_webp_and_jpeg' ).addEventListener( 'change', function () {
				document.querySelector( '.webp-uploads-use-picture-element' ).classList.toggle( 'webp-uploads-disabled', ! this.checked );
				document.getElementById( 'webp_uploads_picture_element_notice' ).hidden = this.checked;
				document.getElementById( 'webp_uploads_use_picture_element' ).classList.toggle( 'disabled', ! this.checked );
				document.getElementById( 'webp_uploads_picture_element_fieldset' ).classList.toggle( 'disabled', ! this.checked );
			} );
		</script>
	<?php
}

/**
 * Renders the settings field for the 'webp_uploads_use_picture_element' setting.
 *
 * @since n.e.x.t
 */
function webp_uploads_use_picture_element_callback(): void {
	// Picture element support requires the JPEG output to be enabled.
	$jpeg_fallback_enabled = webp_uploads_is_jpeg_fallback_enabled();
	?>
	<style>
		#webp_uploads_picture_element_fieldset.disabled label,
		#webp_uploads_picture_element_fieldset.disabled p {
			opacity: 0.7;
		}
	</style>
	<div id="webp_uploads_picture_element_notice" class="notice notice-info inline" <?php echo $jpeg_fallback_enabled ? 'hidden' : ''; ?>>
		<p><?php esc_html_e( 'This setting requires JPEG also be output as a fallback option.', 'webp-uploads' ); ?></p>
	</div>
	<div id="webp_uploads_picture_element_fieldset" class="<?php echo ! $jpeg_fallback_enabled ? 'disabled' : ''; ?>">
		<label for="webp_uploads_use_picture_element" id="webp_uploads_use_picture_element_label">
			<input name="webp_uploads_use_picture_element" type="checkbox" id="webp_uploads_use_picture_element" aria-describedby="webp_uploads_use_picture_element_description" value="1"<?php checked( webp_uploads_is_picture_element_enabled() ); ?> class="<?php echo ! $jpeg_fallback_enabled ? 'disabled' : ''; ?>" >
			<?php esc_html_e( 'Use <picture> Element', 'webp-uploads' ); ?>
			<em><?php esc_html_e( '(experimental)', 'webp-uploads' ); ?></em>
		</label>
		<p class="description" id="webp_uploads_use_picture_element_description"><?php esc_html_e( 'The picture element serves a modern image format with a fallback to JPEG. Warning: Make sure you test your theme and plugins for compatibility. In particular, CSS selectors will not match images when using the child combinator (e.g. figure > img).', 'webp-uploads' ); ?></p>
	</div>
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
		esc_url( admin_url( 'options-media.php#modern-image-formats' ) ),
		esc_html__( 'Settings', 'webp-uploads' )
	);

	return array_merge(
		array( 'settings' => $settings_link ),
		$links
	);
}
add_filter( 'plugin_action_links_' . WEBP_UPLOADS_MAIN_FILE, 'webp_uploads_add_settings_action_link' );
