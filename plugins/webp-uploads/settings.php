<?php
/**
 * Settings for the Modern Image Formats plugin.
 *
 * @package webp-uploads
 *
 * @since 1.0.0
 */

declare( strict_types = 1 );

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Registers setting for generating JPEG in addition to the selected modern format for image uploads.
 *
 * @since 1.0.0
 * @since 2.0.0 The setting was made more general to cover outputting JPEG as a secondary type. The "webp" option naming
 *              was left unchanged for backward compatibility. Also, the `perflab_modern_image_format` was added to
 *              enable selecting an output format. Currently includes AVIF and WebP.
 */
function webp_uploads_register_media_settings_field(): void {
	register_setting(
		'media',
		'perflab_modern_image_format',
		array(
			'sanitize_callback' => 'webp_uploads_sanitize_image_format',
			'type'              => 'string',
			'default'           => 'avif', // AVIF is the default if the editor supports it.
			'show_in_rest'      => false,
		)
	);

	register_setting(
		'media',
		'perflab_generate_webp_and_jpeg',
		array(
			'type'         => 'boolean',
			'default'      => current_theme_supports( 'html5', 'picture' ), // If picture element is supported by the theme, default to enabling the JPEG fallback.
			'show_in_rest' => false,
		)
	);

	// Add a setting to generate fallback images in all sizes including custom sizes.
	register_setting(
		'media',
		'perflab_generate_all_fallback_sizes',
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
		_x( 'Modern Image Formats', 'settings page section name', 'webp-uploads' ),
		static function (): void {
			printf(
				'<p>%s</p>',
				wp_kses(
					sprintf(
						/* translators: %s: URL to the plugin FAQ on WordPress.org */
						__( 'If modern format images are not being generated after upload, see the <a href="%s">FAQ</a> for common reasons.', 'webp-uploads' ),
						'https://wordpress.org/plugins/webp-uploads/#faq'
					),
					array( 'a' => array( 'href' => array() ) )
				)
			);
		},
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

	// Only add the remaining settings fields if at least one modern image format can be generated, either by the server or by the browser.
	if (
		! webp_uploads_mime_type_supported( 'image/avif' ) &&
		! webp_uploads_mime_type_supported( 'image/webp' ) &&
		! webp_uploads_is_client_side_media_processing_enabled()
	) {
		return;
	}

	// Add fallback image output settings field.
	add_settings_field(
		'perflab_generate_webp_and_jpeg',
		__( 'Output fallback images', 'webp-uploads' ),
		'webp_uploads_generate_webp_jpeg_setting_callback',
		'media',
		'perflab_modern_image_format_settings',
		array( 'class' => 'perflab-generate-webp-and-jpeg' )
	);

	// Add setting field for generating fallback images in all sizes including custom sizes.
	add_settings_field(
		'perflab_generate_all_fallback_sizes',
		__( 'Generate all fallback image sizes', 'webp-uploads' ),
		'webp_uploads_generate_all_fallback_sizes_callback',
		'media',
		'perflab_modern_image_format_settings',
		array( 'class' => 'perflab-generate-fallback-all-sizes' )
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
 * Since WordPress 7.1 the block editor can convert uploads in the browser, in which case a format is available even if
 * the server cannot encode it. Whether the browser is able to do so is a runtime check, so the notices are rendered
 * for a capable browser and adjusted with JavaScript when the check fails.
 *
 * @since 2.0.0
 * @since n.e.x.t Formats which the browser can encode via client side media processing are no longer disabled.
 */
function webp_uploads_generate_avif_webp_setting_callback(): void {

	$selected                    = webp_uploads_get_image_output_format();
	$avif_supported              = webp_uploads_avif_supported();
	$avif_transparency_supported = webp_uploads_avif_transparency_supported();
	$avif_fully_supported        = $avif_supported && $avif_transparency_supported;
	$webp_supported              = webp_uploads_mime_type_supported( 'image/webp' );
	$client_side_enabled         = webp_uploads_is_client_side_media_processing_enabled();

	// If neither format is supported by the server and the browser cannot help either, the entire field is not shown.
	if ( ! $avif_fully_supported && ! $webp_supported && ! $client_side_enabled ) {
		webp_uploads_render_modern_image_support_unavailable_notice( false );
		return;
	}

	// If only one of the two formats is supported by the server, the dropdown defaults to that type and the other type is disabled.
	if ( ! $client_side_enabled ) {
		if ( ! $avif_fully_supported && 'avif' === $selected ) {
			$selected = 'webp';
		} elseif ( ! $webp_supported && 'webp' === $selected ) {
			$selected = 'avif';
		}
	}

	?>
	<select name="perflab_modern_image_format" id="perflab_modern_image_format" aria-describedby="perflab_modern_image_format_description" data-selected="<?php echo esc_attr( $selected ); ?>">
		<option value="webp" data-server-supported="<?php echo $webp_supported ? '1' : '0'; ?>"<?php selected( 'webp', $selected ); ?><?php disabled( ! $webp_supported && ! $client_side_enabled ); ?>><?php esc_html_e( 'WebP', 'webp-uploads' ); ?></option>
		<option value="avif" data-server-supported="<?php echo $avif_fully_supported ? '1' : '0'; ?>"<?php selected( 'avif', $selected ); ?><?php disabled( ! $avif_fully_supported && ! $client_side_enabled ); ?>><?php esc_html_e( 'AVIF', 'webp-uploads' ); ?></option>
	</select>
	<label for="perflab_modern_image_format">
		<?php esc_html_e( 'Generate images in this format', 'webp-uploads' ); ?>
	</label>
	<p class="description" id="perflab_modern_image_format_description">
		<?php esc_html_e( 'Select the format to use when generating new images from uploaded images.', 'webp-uploads' ); ?>
		<?php esc_html_e( 'Generated images may be discarded if the file in the modern format is larger than the originally uploaded image.', 'webp-uploads' ); ?>
	</p>
	<?php
	// Notices about the server's capabilities. These are hidden when the browser can encode the format instead.
	?>
	<div id="webp_uploads_avif_unavailable_notice" class="notice notice-warning inline" <?php echo ( $avif_fully_supported || $client_side_enabled ) ? 'hidden' : ''; ?>>
		<?php if ( ! $avif_supported ) : ?>
			<p><b><?php esc_html_e( 'AVIF support is not available.', 'webp-uploads' ); ?></b></p>
			<p><?php esc_html_e( 'AVIF support can only be enabled by your hosting provider, so contact them for more information.', 'webp-uploads' ); ?></p>
		<?php else : ?>
			<p><b><?php esc_html_e( 'AVIF is supported, but not fully: transparency support is lacking.', 'webp-uploads' ); ?></b></p>
			<p><?php esc_html_e( 'Current ImageMagick version does not support transparent AVIF images, so contact your hosting provider for more information.', 'webp-uploads' ); ?></p>
		<?php endif; ?>
	</div>
	<div id="webp_uploads_webp_unavailable_notice" class="notice notice-warning inline" <?php echo ( $webp_supported || $client_side_enabled ) ? 'hidden' : ''; ?>>
		<p><b><?php esc_html_e( 'WebP support is not available.', 'webp-uploads' ); ?></b></p>
		<p><?php esc_html_e( 'WebP support can only be enabled by your hosting provider, so contact them for more information.', 'webp-uploads' ); ?></p>
	</div>
	<?php
	if ( ! $client_side_enabled ) {
		return;
	}

	// Notices about client side media processing. Whether the browser is capable is determined by the script below.
	?>
	<div id="webp_uploads_avif_browser_notice" class="notice notice-info inline" <?php echo $avif_fully_supported ? 'hidden' : ''; ?>>
		<p><b><?php esc_html_e( 'AVIF images are created by your browser.', 'webp-uploads' ); ?></b></p>
		<p>
			<?php
			if ( $webp_supported ) {
				esc_html_e( 'Your server cannot generate AVIF images, but your browser can. Images uploaded in the editor are converted to AVIF in the browser. Images uploaded elsewhere, such as in the Media Library, are converted to WebP on the server instead.', 'webp-uploads' );
			} else {
				esc_html_e( 'Your server cannot generate AVIF images, but your browser can. Images uploaded in the editor are converted to AVIF in the browser. Images uploaded elsewhere, such as in the Media Library, are not converted.', 'webp-uploads' );
			}
			?>
		</p>
	</div>
	<div id="webp_uploads_webp_browser_notice" class="notice notice-info inline" <?php echo $webp_supported ? 'hidden' : ''; ?>>
		<p><b><?php esc_html_e( 'WebP images are created by your browser.', 'webp-uploads' ); ?></b></p>
		<p><?php esc_html_e( 'Your server cannot generate WebP images, but your browser can. Images uploaded in the editor are converted to WebP in the browser. Images uploaded elsewhere, such as in the Media Library, are not converted.', 'webp-uploads' ); ?></p>
	</div>
	<div id="webp_uploads_server_conversion_notice" class="notice notice-info inline" hidden>
		<p><?php esc_html_e( 'Your browser cannot convert images itself, so uploaded images are converted on the server.', 'webp-uploads' ); ?></p>
	</div>
	<?php
	webp_uploads_render_modern_image_support_unavailable_notice( true );

	// phpcs:ignore Squiz.PHP.Heredoc.NotAllowed -- Part of the PCP ruleset. Appealed in <https://github.com/WordPress/plugin-check/issues/792#issuecomment-3214985527>.
	$js  = <<<'JS'
	( function () {
		/**
		 * Detects whether the browser is able to process media client side.
		 *
		 * This mirrors the feature detection of the editor's upload-media package, except for the SharedArrayBuffer
		 * check: SharedArrayBuffer is only available to cross-origin isolated documents, which the editor sets up for
		 * itself but the settings screen does not.
		 *
		 * @return {boolean} Whether the browser can process media client side.
		 */
		function isClientSideMediaProcessingSupported() {
			if ( typeof WebAssembly === 'undefined' || typeof Worker === 'undefined' ) {
				return false;
			}
			if ( 'deviceMemory' in navigator && navigator.deviceMemory <= 2 ) {
				return false;
			}
			if ( 'hardwareConcurrency' in navigator && navigator.hardwareConcurrency < 2 ) {
				return false;
			}
			const connection = navigator.connection;
			if ( connection && ( connection.saveData || 'slow-2g' === connection.effectiveType || '2g' === connection.effectiveType ) ) {
				return false;
			}
			// Security plugins may set a Content Security Policy which blocks the blob: workers used for processing.
			try {
				const url = URL.createObjectURL( new Blob( [ '' ], { type: 'application/javascript' } ) );
				try {
					new Worker( url ).terminate();
				} finally {
					URL.revokeObjectURL( url );
				}
			} catch ( error ) {
				return false;
			}
			return true;
		}

		if ( isClientSideMediaProcessingSupported() ) {
			return;
		}

		// The browser cannot help, so fall back to what the server supports.
		const select = document.getElementById( 'perflab_modern_image_format' );
		const options = Array.from( select.options );
		let serverSupportsAny = false;
		for ( const option of options ) {
			const serverSupported = '1' === option.dataset.serverSupported;
			option.disabled = ! serverSupported;
			serverSupportsAny = serverSupportsAny || serverSupported;
			document.getElementById( 'webp_uploads_' + option.value + '_browser_notice' ).hidden = true;
			document.getElementById( 'webp_uploads_' + option.value + '_unavailable_notice' ).hidden = serverSupported;
		}
		document.getElementById( 'webp_uploads_server_conversion_notice' ).hidden = ! serverSupportsAny;

		if ( ! serverSupportsAny ) {
			select.hidden = true;
			select.disabled = true;
			select.labels.forEach( ( label ) => { label.hidden = true; } );
			document.getElementById( 'perflab_modern_image_format_description' ).hidden = true;
			document.getElementById( 'webp_uploads_modern_image_support_unavailable_notice' ).hidden = false;
			return;
		}

		// Make sure a supported format is selected, so that the submitted value is not lost.
		if ( select.selectedOptions.length === 0 || select.selectedOptions[ 0 ].disabled ) {
			select.value = options.find( ( option ) => ! option.disabled ).value;
		}
	} )();
	JS;
	$js .= "\n//# sourceURL=webp-uploads-settings-client-side-media-processing";
	wp_print_inline_script_tag( $js, array( 'type' => 'module' ) );
}

/**
 * Renders the notice shown when no modern image format can be generated.
 *
 * @since n.e.x.t
 *
 * @param bool $hidden Whether the notice is initially hidden.
 */
function webp_uploads_render_modern_image_support_unavailable_notice( bool $hidden ): void {
	?>
	<div id="webp_uploads_modern_image_support_unavailable_notice" class="notice notice-warning inline" <?php echo $hidden ? 'hidden' : ''; ?>>
		<p><b><?php esc_html_e( 'Modern image support is not available.', 'webp-uploads' ); ?></b></p>
		<p><?php esc_html_e( 'WebP or AVIF support can only be enabled by your hosting provider, so contact them for more information.', 'webp-uploads' ); ?></p>
	</div>
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
			<?php esc_html_e( 'Also generate fallback images in the original upload format', 'webp-uploads' ); ?>
		</label>
		<p class="description" id="perflab_generate_webp_and_jpeg_description"><?php esc_html_e( 'Enabling fallback image output can improve compatibility, but will increase the filesystem storage use of your images.', 'webp-uploads' ); ?></p>
	<?php
}


/**
 * Renders the settings field for generating all fallback image sizes.
 *
 * @since 2.4.0
 */
function webp_uploads_generate_all_fallback_sizes_callback(): void {
	$all_fallback_sizes_enabled   = webp_uploads_should_generate_all_fallback_sizes();
	$fallback_enabled             = webp_uploads_is_fallback_enabled();
	$all_fallback_sizes_hidden_id = 'perflab_generate_all_fallback_sizes_hidden';

	?>
	<style>
		#perflab_generate_all_fallback_sizes_fieldset.disabled label,
		#perflab_generate_all_fallback_sizes_fieldset.disabled p {
			opacity: 0.7;
		}
	</style>
	<div id="perflab_generate_all_fallback_sizes_notice" class="notice notice-info inline" <?php echo $fallback_enabled ? 'hidden' : ''; ?>>
		<p><?php esc_html_e( 'This setting requires fallback image output to be enabled.', 'webp-uploads' ); ?></p>
	</div>
	<div id="perflab_generate_all_fallback_sizes_fieldset" class="<?php echo ! $fallback_enabled ? 'disabled' : ''; ?>">
		<label for="perflab_generate_all_fallback_sizes" id="perflab_generate_all_fallback_sizes_label">
			<input
				type="checkbox"
				id="perflab_generate_all_fallback_sizes"
				name="perflab_generate_all_fallback_sizes"
				aria-describedby="perflab_generate_all_fallback_sizes_description"
				value="1"
				<?php checked( $all_fallback_sizes_enabled ); ?>
				<?php disabled( ! $fallback_enabled ); ?>
			>
			<?php
			/*
			 * If the checkbox is disabled, but the option is enabled, include a hidden input to continue sending the
			 * same value upon form submission.
			 */
			if ( ! $fallback_enabled && $all_fallback_sizes_enabled ) {
				?>
				<input
					type="hidden"
					id="<?php echo esc_attr( $all_fallback_sizes_hidden_id ); ?>"
					name="perflab_generate_all_fallback_sizes"
					value="1"
				>
				<?php
			}
			esc_html_e( 'Generate all fallback image sizes including custom sizes', 'webp-uploads' );
			?>
		</label>
		<p class="description" id="perflab_generate_all_fallback_sizes_description"><?php esc_html_e( 'Enabling this option will generate all fallback image sizes including custom sizes. Note: uses even more storage space.', 'webp-uploads' ); ?></p>
	</div>
	<script>
	( function ( allFallbackSizesHiddenId ) {
		const fallbackCheckbox = document.getElementById( 'perflab_generate_webp_and_jpeg' );
		const allFallbackSizesCheckbox = document.getElementById( 'perflab_generate_all_fallback_sizes' );
		const allFallbackSizesFieldset = document.getElementById( 'perflab_generate_all_fallback_sizes_fieldset' );
		const allFallbackSizesNotice = document.getElementById( 'perflab_generate_all_fallback_sizes_notice' );

		function toggleAllFallbackSizes() {
			const fallbackEnabled = fallbackCheckbox.checked;
			allFallbackSizesFieldset.classList.toggle( 'disabled', ! fallbackEnabled );
			allFallbackSizesCheckbox.disabled = ! fallbackEnabled;
			allFallbackSizesNotice.hidden = fallbackEnabled;

			// Remove or inject hidden input to preserve original setting value as needed.
			if ( fallbackEnabled ) {
				const hiddenInput = document.getElementById( allFallbackSizesHiddenId );
				if ( hiddenInput ) {
					hiddenInput.parentElement.removeChild( hiddenInput );
				}
			} else if ( allFallbackSizesCheckbox.checked && ! document.getElementById( allFallbackSizesHiddenId ) ) {
				// The hidden input is only needed if the value was originally set (i.e., the checkbox enabled).
				const hiddenInput = document.createElement( 'input' );
				hiddenInput.type = 'hidden';
				hiddenInput.id = allFallbackSizesHiddenId;
				hiddenInput.name = allFallbackSizesCheckbox.name;
				hiddenInput.value = allFallbackSizesCheckbox.value;
				allFallbackSizesCheckbox.parentElement.insertBefore( hiddenInput, allFallbackSizesCheckbox.nextSibling );
			}
		}

		fallbackCheckbox.addEventListener( 'change', toggleAllFallbackSizes );
	} )( <?php echo wp_json_encode( $all_fallback_sizes_hidden_id, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ); ?> );
	</script>
	<?php
}

/**
 * Renders the settings field for the 'webp_uploads_use_picture_element' setting.
 *
 * @since 2.0.0
 */
function webp_uploads_use_picture_element_callback(): void {
	// Picture element support requires the JPEG output to be enabled.
	$picture_element_option    = 1 === (int) get_option( 'webp_uploads_use_picture_element', 0 );
	$jpeg_fallback_enabled     = webp_uploads_is_fallback_enabled();
	$picture_element_hidden_id = 'webp_uploads_use_picture_element_hidden';
	?>
	<style>
		#webp_uploads_picture_element_fieldset.disabled label,
		#webp_uploads_picture_element_fieldset.disabled p {
			opacity: 0.7;
		}
	</style>
	<div id="webp_uploads_picture_element_notice" class="notice notice-info inline" <?php echo $jpeg_fallback_enabled ? 'hidden' : ''; ?>>
		<p><?php esc_html_e( 'This setting requires fallback image output to be enabled.', 'webp-uploads' ); ?></p>
	</div>
	<div id="webp_uploads_picture_element_fieldset" class="<?php echo ! $jpeg_fallback_enabled ? 'disabled' : ''; ?>">
		<label for="webp_uploads_use_picture_element" id="webp_uploads_use_picture_element_label">
			<input
				type="checkbox"
				id="webp_uploads_use_picture_element"
				name="webp_uploads_use_picture_element"
				aria-describedby="webp_uploads_use_picture_element_description"
				value="1"
				<?php checked( $picture_element_option ); // Option intentionally used instead of webp_uploads_is_picture_element_enabled() to persist when perflab_generate_webp_and_jpeg is updated. ?>
				<?php disabled( ! $jpeg_fallback_enabled ); ?>
			>
			<?php
			/*
			 * If the checkbox is disabled, but the option is enabled, include a hidden input to continue sending the
			 * same value upon form submission.
			 */
			if ( ! $jpeg_fallback_enabled && $picture_element_option ) {
				?>
				<input
					type="hidden"
					id="<?php echo esc_attr( $picture_element_hidden_id ); ?>"
					name="webp_uploads_use_picture_element"
					value="1"
				>
				<?php
			}
			esc_html_e( 'Use <picture> Element', 'webp-uploads' );
			?>
			<em><?php esc_html_e( '(experimental)', 'webp-uploads' ); ?></em>
		</label>
		<p class="description" id="webp_uploads_use_picture_element_description"><?php esc_html_e( 'The picture element serves a modern image format with a fallback to the original upload format. Warning: Make sure you test your theme and plugins for compatibility. In particular, CSS selectors will not match images when using the child combinator (e.g. figure > img).', 'webp-uploads' ); ?></p>
		<div id="webp_uploads_jpeg_fallback_notice" class="notice notice-info inline" <?php echo $picture_element_option ? '' : 'hidden'; ?>>
			<p><?php esc_html_e( 'Picture elements will only be used when fallback images are available. So this will only apply to  images you have uploaded while the "Also generate fallback images" setting was enabled.', 'webp-uploads' ); ?></p>
		</div>
	</div>
	<script>
	( function ( pictureElementHiddenId ) {
		document.getElementById( 'webp_uploads_use_picture_element' ).addEventListener( 'change', function () {
			document.getElementById( 'webp_uploads_jpeg_fallback_notice' ).hidden = ! this.checked;
		} );

		// Listen for clicks on the fallback output checkbox, enabling/disabling the
		// picture element checkbox accordingly.
		document.getElementById( 'perflab_generate_webp_and_jpeg' ).addEventListener( 'change', function () {
			document.querySelector( '.webp-uploads-use-picture-element' ).classList.toggle( 'webp-uploads-disabled', ! this.checked );
			document.getElementById( 'webp_uploads_picture_element_notice' ).hidden = this.checked;
			document.getElementById( 'webp_uploads_picture_element_fieldset' ).classList.toggle( 'disabled', ! this.checked );

			const checkbox = document.getElementById( 'webp_uploads_use_picture_element' );
			checkbox.disabled = ! this.checked;

			// Remove or inject hidden input to preserve original setting value as needed.
			if ( this.checked ) {
				const hiddenInput = document.getElementById( pictureElementHiddenId );
				if ( hiddenInput ) {
					hiddenInput.parentElement.removeChild( hiddenInput );
				}
			} else if ( checkbox.checked && ! document.getElementById( pictureElementHiddenId ) ) {
				// The hidden input is only needed if the value was originally set (i.e. the checkbox enabled).
				const hiddenInput = document.createElement( 'input' );
				hiddenInput.type = 'hidden';
				hiddenInput.id = pictureElementHiddenId;
				hiddenInput.name = checkbox.name;
				hiddenInput.value = checkbox.value;
				checkbox.parentElement.insertBefore( hiddenInput, checkbox.nextSibling );
			}
		} );
	} )( <?php echo wp_json_encode( $picture_element_hidden_id, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ); ?> );
	</script>
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
