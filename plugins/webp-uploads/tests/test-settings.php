<?php
/**
 * Tests for webp-uploads plugin settings.php.
 *
 * @package webp-uploads
 */

class Test_WebP_Uploads_Settings extends WP_UnitTestCase {

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
	 * @covers ::webp_uploads_register_media_settings_field
	 */
	public function test_webp_uploads_register_media_settings_field(): void {
		webp_uploads_register_media_settings_field();

		$registered_settings = get_registered_settings();

		$this->assertArrayHasKey( 'perflab_modern_image_format', $registered_settings );
		$this->assertArrayHasKey( 'perflab_generate_webp_and_jpeg', $registered_settings );
		$this->assertArrayHasKey( 'perflab_generate_all_fallback_sizes', $registered_settings );
		$this->assertArrayHasKey( 'webp_uploads_use_picture_element', $registered_settings );
	}

	/**
	 * @covers ::webp_uploads_add_media_settings_fields
	 */
	public function test_webp_uploads_add_media_settings_fields(): void {
		webp_uploads_add_media_settings_fields();

		$this->assertNotFalse( has_action( 'admin_init', 'webp_uploads_add_media_settings_fields' ) );
	}

	/**
	 * @covers ::webp_uploads_generate_webp_jpeg_setting_callback
	 */
	public function test_webp_uploads_generate_webp_jpeg_setting_callback(): void {
		$output = get_echo( 'webp_uploads_generate_webp_jpeg_setting_callback' );
		$this->assertStringContainsString( 'perflab_generate_webp_and_jpeg', $output );
	}

	/**
	 * @covers ::webp_uploads_generate_all_fallback_sizes_callback
	 */
	public function test_webp_uploads_generate_all_fallback_sizes_callback(): void {
		$output = get_echo( 'webp_uploads_generate_all_fallback_sizes_callback' );
		$this->assertStringContainsString( 'perflab_generate_all_fallback_sizes', $output );
	}

	/**
	 * @covers ::webp_uploads_use_picture_element_callback
	 */
	public function test_webp_uploads_use_picture_element_callback(): void {
		$output = get_echo( 'webp_uploads_use_picture_element_callback' );
		$this->assertStringContainsString( 'webp_uploads_use_picture_element', $output );
	}

	/**
	 * @covers ::webp_uploads_generate_avif_webp_setting_callback
	 */
	public function test_webp_uploads_generate_avif_webp_setting_callback(): void {
		$output = get_echo( 'webp_uploads_generate_avif_webp_setting_callback' );

		// Check for either the format selector or the notice about modern image support not being available.
		$has_format_selector   = strpos( $output, 'perflab_modern_image_format' ) !== false;
		$has_no_support_notice = strpos( $output, 'Modern image support is not available' ) !== false;

		$this->assertTrue(
			$has_format_selector || $has_no_support_notice,
			'Output should either contain the format selector or the notice about modern image support not being available'
		);
	}
}
