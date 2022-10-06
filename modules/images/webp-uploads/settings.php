<?php
/**
 * Settings for the WebP Uploads module.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Registers setting for generating both JPEG and WebP versions for image uploads.
 *
 * @since n.e.x.t
 */
function webp_uploads_register_media_settings_field() {
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
 * @since n.e.x.t
 */
function webp_uploads_add_media_settings_field() {
	// Add settings field.
	add_settings_field(
		'perflab_generate_webp_and_jpeg',
		__( 'Generate WebP and JPEG', 'performance-lab' ),
		'webp_uploads_generate_webp_jpeg_setting_callback',
		'media',
		'uploads',
		array( 'class' => 'perflab-generate-webp-and-jpeg' )
	);
}
add_action( 'admin_init', 'webp_uploads_add_media_settings_field' );

/**
 * Renders the settings field for the 'perflab_generate_webp_and_jpeg' setting.
 *
 * @since n.e.x.t
 */
function webp_uploads_generate_webp_jpeg_setting_callback() {
	?>
	<label for="perflab_generate_webp_and_jpeg">
		<input name="perflab_generate_webp_and_jpeg" type="checkbox" id="perflab_generate_webp_and_jpeg" aria-describedby="perflab_generate_webp_and_jpeg_description" value="1"<?php checked( '1', get_option( 'perflab_generate_webp_and_jpeg' ) ); ?> />
		<?php esc_html_e( 'Generate JPEG files in addition to WebP', 'performance-lab' ); ?>
	</label>
	<p class="description" id="perflab_generate_webp_and_jpeg_description"><?php esc_html_e( 'Enabling JPEG in addition to WebP can improve compatibility, but will effectively double the filesystem storage use of your images.', 'performance-lab' ); ?></p>
	<?php
}

/**
 * Adds custom style for media settings.
 *
 * @since n.e.x.t
 *
 * @global string $pagenow The filename of the current screen.
 */
function webp_uploads_media_setting_style() {
	global $pagenow;

	if ( 'options-media.php' === $pagenow ) {
		echo '<style>
		.form-table .perflab-generate-webp-and-jpeg th {
			display: none;
		}
		.form-table .perflab-generate-webp-and-jpeg td {
			width: auto;
			padding-left: 0;
		}
		</style>';
	}
}
add_action( 'admin_head', 'webp_uploads_media_setting_style' );
