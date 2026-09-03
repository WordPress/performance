<?php
/**
 * Tests for webp-uploads plugin settings.php.
 *
 * @package webp-uploads
 */

use WebP_Uploads\Tests\TestCase;

class Test_WebP_Uploads_Settings extends TestCase {

	/**
	 * @covers ::webp_uploads_add_media_settings_fields
	 */
	public function test_webp_uploads_add_media_settings_fields_section_description(): void {
		global $wp_settings_sections;
		webp_uploads_add_media_settings_fields();

		$callback = $wp_settings_sections['media']['perflab_modern_image_format_settings']['callback'];
		$this->assertIsCallable( $callback );

		$processor = new WP_HTML_Tag_Processor( get_echo( $callback ) );
		$this->assertTrue( $processor->next_tag( 'A' ) );
		$this->assertSame( 'https://wordpress.org/plugins/webp-uploads/#faq', $processor->get_attribute( 'href' ) );
	}

	/**
	 * @covers ::webp_uploads_add_settings_action_link
	 */
	public function test_webp_uploads_add_settings_action_link(): void {
		$this->assertSame( 10, has_filter( 'plugin_action_links_' . WEBP_UPLOADS_MAIN_FILE, 'webp_uploads_add_settings_action_link' ) );
		$this->assertFalse( webp_uploads_add_settings_action_link( false ) );

		$default_action_links = array(
			'deactivate' => '<a href="plugins.php?action=deactivate&amp;plugin=webp-uploads%2Fload.php&amp;plugin_status=all&amp;paged=1&amp;s&amp;_wpnonce=48f74bdd74" id="deactivate-webp-uploads" aria-label="Deactivate Modern Image Formats">Deactivate</a>',
		);

		$this->assertSame(
			array_merge(
				array(
					'settings' => '<a href="' . esc_url( admin_url( 'options-media.php#modern-image-formats' ) ) . '">Settings</a>',
				),
				$default_action_links
			),
			webp_uploads_add_settings_action_link( $default_action_links )
		);
	}

	/**
	 * @covers ::webp_uploads_generate_avif_webp_setting_callback
	 */
	public function test_webp_uploads_generate_avif_webp_setting_callback_disables_avif_and_shows_warning(): void {
		$editor = _wp_image_editor_choose( array( 'mime_type' => 'image/avif' ) );
		if ( ! is_string( $editor ) || ! webp_uploads_imagick_avif_supported( $editor ) ) {
			$this->markTestSkipped( 'Test requires WP_Image_Editor_Imagick.' );
		}

		$this->set_image_output_type( 'avif' );
		$this->mock_avif_transparency_support( false );
		add_filter( 'wp_client_side_media_processing_enabled', '__return_false' );
		$output    = get_echo( 'webp_uploads_generate_avif_webp_setting_callback' );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'OPTION' ) ) );
		$this->assertSame( 'webp', $processor->get_attribute( 'value' ) );
		$this->assertNotNull( $processor->get_attribute( 'selected' ) );

		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'OPTION' ) ) );
		$this->assertSame( 'avif', $processor->get_attribute( 'value' ) );
		$this->assertNotNull( $processor->get_attribute( 'disabled' ) );
		$this->assertNull( $processor->get_attribute( 'selected' ) );

		$this->assertStringContainsString( 'AVIF is supported, but not fully: transparency support is lacking.', $output );
		$this->assertStringContainsString( 'Current ImageMagick version does not support transparent AVIF images', $output );
	}

	/**
	 * Forces the availability of client side media processing for the site.
	 *
	 * @param bool $enabled Whether client side media processing is enabled.
	 */
	private function mock_client_side_media_processing( bool $enabled ): void {
		if ( ! function_exists( 'wp_is_client_side_media_processing_enabled' ) ) {
			$this->markTestSkipped( 'Test requires client side media processing support in WordPress core.' );
		}
		add_filter(
			'wp_client_side_media_processing_enabled',
			static function () use ( $enabled ): bool {
				return $enabled;
			}
		);
		$this->assertSame( $enabled, webp_uploads_is_client_side_media_processing_enabled() );
	}

	/**
	 * Removes server support for modern image formats.
	 */
	private function remove_server_modern_image_support(): void {
		add_filter(
			'wp_image_editors',
			static function () {
				return array( 'WP_Image_Doesnt_Support_Modern_Images' );
			}
		);
		$this->assertFalse( webp_uploads_mime_type_supported( 'image/avif' ) );
		$this->assertFalse( webp_uploads_mime_type_supported( 'image/webp' ) );
	}

	/**
	 * @covers ::webp_uploads_generate_avif_webp_setting_callback
	 * @covers ::webp_uploads_render_modern_image_support_unavailable_notice
	 */
	public function test_webp_uploads_generate_avif_webp_setting_callback_shows_only_notice_without_any_support(): void {
		$this->remove_server_modern_image_support();
		$this->mock_client_side_media_processing( false );

		$output    = get_echo( 'webp_uploads_generate_avif_webp_setting_callback' );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertFalse( $processor->next_tag( 'SELECT' ) );
		$this->assertStringContainsString( 'Modern image support is not available.', $output );
		$this->assertStringNotContainsString( 'hidden', $output );
	}

	/**
	 * @covers ::webp_uploads_generate_avif_webp_setting_callback
	 * @covers ::webp_uploads_render_modern_image_support_unavailable_notice
	 */
	public function test_webp_uploads_generate_avif_webp_setting_callback_keeps_formats_selectable_with_client_side_media_processing(): void {
		$this->remove_server_modern_image_support();
		$this->mock_client_side_media_processing( true );
		$this->set_image_output_type( 'avif' );

		$output    = get_echo( 'webp_uploads_generate_avif_webp_setting_callback' );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'SELECT' ) );
		$this->assertSame( 'avif', $processor->get_attribute( 'data-selected' ) );

		$this->assertTrue( $processor->next_tag( 'OPTION' ) );
		$this->assertSame( 'webp', $processor->get_attribute( 'value' ) );
		$this->assertSame( '0', $processor->get_attribute( 'data-server-supported' ) );
		$this->assertNull( $processor->get_attribute( 'disabled' ) );
		$this->assertNull( $processor->get_attribute( 'selected' ) );

		$this->assertTrue( $processor->next_tag( 'OPTION' ) );
		$this->assertSame( 'avif', $processor->get_attribute( 'value' ) );
		$this->assertSame( '0', $processor->get_attribute( 'data-server-supported' ) );
		$this->assertNull( $processor->get_attribute( 'disabled' ) );
		$this->assertNotNull( $processor->get_attribute( 'selected' ) );

		$notices = array();
		while ( $processor->next_tag( 'DIV' ) ) {
			$notices[ (string) $processor->get_attribute( 'id' ) ] = null === $processor->get_attribute( 'hidden' ) ? 'visible' : 'hidden';
		}
		$this->assertSame(
			array(
				'webp_uploads_avif_unavailable_notice'  => 'hidden',
				'webp_uploads_webp_unavailable_notice'  => 'hidden',
				'webp_uploads_avif_browser_notice'      => 'visible',
				'webp_uploads_webp_browser_notice'      => 'visible',
				'webp_uploads_server_conversion_notice' => 'hidden',
				'webp_uploads_modern_image_support_unavailable_notice' => 'hidden',
			),
			$notices
		);

		$this->assertStringContainsString( 'AVIF images are created by your browser.', $output );
		$this->assertStringContainsString( 'are not converted.', $output );
		$this->assertStringContainsString( '<script>', $output );
	}

	/**
	 * @covers ::webp_uploads_generate_avif_webp_setting_callback
	 */
	public function test_webp_uploads_generate_avif_webp_setting_callback_shows_server_notices_without_client_side_media_processing(): void {
		$this->remove_server_modern_image_support();
		$this->mock_client_side_media_processing( false );

		// With WebP support only, AVIF is disabled and the selection falls back to WebP.
		add_filter(
			'wp_image_editors',
			static function () {
				return array( 'WP_Image_Editor_GD' );
			},
			11
		);
		if ( ! webp_uploads_mime_type_supported( 'image/webp' ) ) {
			$this->markTestSkipped( 'Test requires WebP support in WP_Image_Editor_GD.' );
		}
		add_filter( 'webp_uploads_imagick_avif_transparency_supported', '__return_false' );
		remove_all_filters( 'webp_uploads_client_side_media_processing' );

		$this->set_image_output_type( 'avif' );
		$avif_fully_supported = webp_uploads_mime_type_supported( 'image/avif' );

		$output    = get_echo( 'webp_uploads_generate_avif_webp_setting_callback' );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'SELECT' ) );
		$options = array();
		while ( $processor->next_tag( 'OPTION' ) ) {
			$options[ (string) $processor->get_attribute( 'value' ) ] = array(
				'disabled' => null !== $processor->get_attribute( 'disabled' ),
				'selected' => null !== $processor->get_attribute( 'selected' ),
			);
		}
		$this->assertSame(
			array(
				'webp' => array(
					'disabled' => false,
					'selected' => ! $avif_fully_supported,
				),
				'avif' => array(
					'disabled' => ! $avif_fully_supported,
					'selected' => $avif_fully_supported,
				),
			),
			$options
		);

		$this->assertStringNotContainsString( 'created by your browser', $output );
		$this->assertStringNotContainsString( '<script>', $output );
	}

	/**
	 * @covers ::webp_uploads_add_media_settings_fields
	 */
	public function test_webp_uploads_add_media_settings_fields_registers_fallback_fields_with_client_side_media_processing(): void {
		global $wp_settings_fields;

		$this->remove_server_modern_image_support();
		$this->mock_client_side_media_processing( false );

		unset( $wp_settings_fields['media']['perflab_modern_image_format_settings'] );
		webp_uploads_add_media_settings_fields();
		$this->assertSame(
			array( 'perflab_modern_image_format' ),
			array_keys( $wp_settings_fields['media']['perflab_modern_image_format_settings'] )
		);

		remove_all_filters( 'wp_client_side_media_processing_enabled' );
		$this->mock_client_side_media_processing( true );

		unset( $wp_settings_fields['media']['perflab_modern_image_format_settings'] );
		webp_uploads_add_media_settings_fields();
		$this->assertSame(
			array( 'perflab_modern_image_format', 'perflab_generate_webp_and_jpeg', 'perflab_generate_all_fallback_sizes', 'webp_uploads_use_picture_element' ),
			array_keys( $wp_settings_fields['media']['perflab_modern_image_format_settings'] )
		);
	}
}
